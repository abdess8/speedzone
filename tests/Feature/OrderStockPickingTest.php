<?php

use App\Enums\PaymentMethod;
use App\Enums\StockMovementSource;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\StockAdjustment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;
use Tests\Support\StockFixtures;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->city = City::query()->create([
        'name' => 'Pick City',
        'code' => 'PKC',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $this->sector = Sector::query()->create([
        'city_id' => $this->city->id,
        'name' => 'Pick Sector',
        'delivery_price' => 25.00,
        'is_active' => true,
    ]);
});

/**
 * @param  array<int, array{product_id: int, quantity: int}>  $items
 * @return array<string, mixed>
 */
function pickingPayload(City $city, Sector $sector, array $items, array $overrides = []): array
{
    return array_merge([
        'customer_first_name' => 'Amina',
        'customer_last_name' => 'Bennani',
        'customer_phone' => '0611111111',
        'customer_address' => '5 rue des Fleurs',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'items' => $items,
    ], $overrides);
}

test('an order built from stock computes its own amount and debits the catalog', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $mug = StockFixtures::product($seller, $store, [
        'name' => 'Mug',
        'sku' => 'MUG-001',
        'unit_price' => 80,
        'stock_quantity' => 10,
    ]);
    $tee = StockFixtures::product($seller, $store, [
        'name' => 'Tee',
        'sku' => 'TEE-001',
        'unit_price' => 150,
        'stock_quantity' => 4,
    ]);

    $this->actingAs($seller)
        ->post('/orders', pickingPayload($this->city, $this->sector, [
            ['product_id' => $mug->id, 'quantity' => 3],
            ['product_id' => $tee->id, 'quantity' => 2],
        ]))
        ->assertSessionHasNoErrors();

    $order = Order::acrossStores()->latest('id')->firstOrFail();

    // 3 × 80 + 2 × 150, computed server side: the amount posted by the browser is
    // never trusted for a stock order.
    expect((float) $order->order_amount)->toBe(540.0)
        ->and($order->items()->count())->toBe(2)
        ->and($mug->fresh()->stock_quantity)->toBe(7)
        ->and($tee->fresh()->stock_quantity)->toBe(2);

    $line = $order->items()->where('product_id', $mug->id)->firstOrFail();

    // The line snapshots what was sold, so a later price change cannot rewrite
    // the history of this order.
    expect($line->product_name)->toBe('Mug')
        ->and($line->sku)->toBe('MUG-001')
        ->and((float) $line->unit_price)->toBe(80.0)
        ->and((float) $line->line_total)->toBe(240.0);
});

test('a global discount is subtracted from the computed amount', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['unit_price' => 200, 'stock_quantity' => 5]);

    $this->actingAs($seller)
        ->post('/orders', pickingPayload(
            $this->city,
            $this->sector,
            [['product_id' => $product->id, 'quantity' => 3]],
            ['discount_amount' => 100]
        ))
        ->assertSessionHasNoErrors();

    $order = Order::acrossStores()->latest('id')->firstOrFail();

    expect((float) $order->order_amount)->toBe(500.0)
        ->and((float) $order->discount_amount)->toBe(100.0);
});

test('an order cannot be built from a product that is out of stock', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['stock_quantity' => 0]);

    $this->actingAs($seller)
        ->post('/orders', pickingPayload($this->city, $this->sector, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]))
        ->assertSessionHasErrors('items');

    expect(Order::acrossStores()->count())->toBe(0);
});

test('an order cannot ask for more units than the shelf holds', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['stock_quantity' => 2]);

    $this->actingAs($seller)
        ->post('/orders', pickingPayload($this->city, $this->sector, [
            ['product_id' => $product->id, 'quantity' => 3],
        ]))
        ->assertSessionHasErrors('items');

    expect($product->fresh()->stock_quantity)->toBe(2)
        ->and(Order::acrossStores()->count())->toBe(0);
});

test('the same product listed twice draws from one shelf', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['stock_quantity' => 5]);

    $this->actingAs($seller)
        ->post('/orders', pickingPayload($this->city, $this->sector, [
            ['product_id' => $product->id, 'quantity' => 3],
            ['product_id' => $product->id, 'quantity' => 3],
        ]))
        ->assertSessionHasErrors('items');

    expect($product->fresh()->stock_quantity)->toBe(5)
        ->and(Order::acrossStores()->count())->toBe(0);
});

test('a quarantined product cannot be picked into an order', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['stock_quantity' => 10]);

    $this->actingAs(StockFixtures::user(Role::ADMIN))
        ->put("/products/{$product->id}/block", ['blocked' => true, 'reason' => 'Defective batch']);

    $this->actingAs($seller)
        ->post('/orders', pickingPayload($this->city, $this->sector, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]))
        ->assertSessionHasErrors('items.0.product_id');

    expect($product->fresh()->stock_quantity)->toBe(10);
});

test('a product from another shop cannot be picked into an order', function () {
    $seller = StockFixtures::user(Role::SELLER);
    StockFixtures::store($seller);

    $intruder = StockFixtures::user(Role::SELLER);
    $foreignStore = StockFixtures::store($intruder, 'Foreign shop');
    $foreignProduct = StockFixtures::product($intruder, $foreignStore, ['stock_quantity' => 10]);

    $this->actingAs($seller)
        ->post('/orders', pickingPayload($this->city, $this->sector, [
            ['product_id' => $foreignProduct->id, 'quantity' => 1],
        ]))
        ->assertSessionHasErrors('items.0.product_id');

    expect($foreignProduct->fresh()->stock_quantity)->toBe(10);
});

test('the debit is journalled against the order that caused it', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['stock_quantity' => 9]);

    $this->actingAs($seller)->post('/orders', pickingPayload($this->city, $this->sector, [
        ['product_id' => $product->id, 'quantity' => 4],
    ]));

    $order = Order::acrossStores()->latest('id')->firstOrFail();
    $movement = StockAdjustment::acrossStores()->where('product_id', $product->id)->firstOrFail();

    expect($movement->source)->toBe(StockMovementSource::ORDER)
        ->and($movement->order_id)->toBe($order->id)
        ->and($movement->delta)->toBe(-4)
        ->and($movement->stock_before)->toBe(9)
        ->and($movement->stock_after)->toBe(5);
});

test('deleting an order puts its units back on the shelf', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['stock_quantity' => 8]);

    $this->actingAs($seller)->post('/orders', pickingPayload($this->city, $this->sector, [
        ['product_id' => $product->id, 'quantity' => 5],
    ]));

    $order = Order::acrossStores()->latest('id')->firstOrFail();

    expect($product->fresh()->stock_quantity)->toBe(3);

    $this->actingAs($seller)->delete("/orders/{$order->id}")->assertSessionHasNoErrors();

    expect($product->fresh()->stock_quantity)->toBe(8)
        // Restored, not un-journalled: the debit and the credit both stay on the
        // ledger so the movement can still be explained afterwards.
        ->and(StockAdjustment::acrossStores()->where('product_id', $product->id)->count())->toBe(2);
});

test('the order sheet carries the picked lines, at the price they were sold', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, [
        'name' => 'Lampe',
        'sku' => 'LMP-001',
        'unit_price' => 120,
        'stock_quantity' => 6,
    ]);

    $this->actingAs($seller)->post('/orders', pickingPayload(
        $this->city,
        $this->sector,
        [['product_id' => $product->id, 'quantity' => 2]],
        ['discount_amount' => 40]
    ));

    $order = Order::acrossStores()->latest('id')->firstOrFail();

    // The catalog moves on after the sale; the sheet must not.
    $product->update(['name' => 'Lampe (ancien modèle)', 'unit_price' => 150]);

    $this->actingAs($seller)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.discount_amount', 40)
            ->has('order.items', 1)
            ->where('order.items.0.name', 'Lampe')
            ->where('order.items.0.sku', 'LMP-001')
            ->where('order.items.0.unit_price', 120)
            ->where('order.items.0.quantity', 2)
            ->where('order.items.0.line_total', 240)
        );
});

test('a parcel-only order carries no lines to display', function () {
    $seller = StockFixtures::user(Role::SELLER);
    StockFixtures::store($seller);

    $this->actingAs($seller)
        ->post('/orders', pickingPayload($this->city, $this->sector, [], ['order_amount' => 320]));

    $order = Order::acrossStores()->latest('id')->firstOrFail();

    $this->actingAs($seller)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('order.items', 0));
});

test('an order without items keeps the amount the vendor typed', function () {
    $seller = StockFixtures::user(Role::SELLER);
    StockFixtures::store($seller);

    $this->actingAs($seller)
        ->post('/orders', pickingPayload($this->city, $this->sector, [], ['order_amount' => 320]))
        ->assertSessionHasNoErrors();

    $order = Order::acrossStores()->latest('id')->firstOrFail();

    expect((float) $order->order_amount)->toBe(320.0)
        ->and($order->items()->count())->toBe(0);
});
