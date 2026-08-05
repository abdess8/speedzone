<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransferContentType;
use App\Enums\TransferStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\TransferService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Support\StockFixtures;

/**
 * The fulfilment route, end to end.
 *
 * An order picked from a vendor's stock never travels to us — the goods already
 * sit in our depot — so it skips the pickup entirely and waits to be packed
 * instead. What happens after packing depends on one thing only: whether the
 * depot stands in the customer's city.
 *
 * Both branches are covered here, because they are the two halves of the same
 * decision and a change to one silently breaks the other.
 */
beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->hub = StockFixtures::hubCity();

    $this->hubSector = Sector::query()->create([
        'city_id' => $this->hub->id,
        'name' => 'Hub Sector',
        'delivery_price' => 20.00,
        'is_active' => true,
    ]);

    $this->faraway = City::query()->create([
        'name' => 'Faraway',
        'code' => 'FAR',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $this->farawaySector = Sector::query()->create([
        'city_id' => $this->faraway->id,
        'name' => 'Faraway Sector',
        'delivery_price' => 35.00,
        'is_active' => true,
    ]);
});

/**
 * A courier working one sector, which is how the assignment service finds him.
 */
function preparationDriver(Sector $sector): User
{
    $driver = StockFixtures::user(Role::DRIVER);
    $driver->sectors()->syncWithoutDetaching([$sector->id]);

    return $driver->fresh();
}

/**
 * A vendor standing on a shop that warehouses in our hub, with stock on the
 * shelf, and one order placed against it.
 *
 * @return array{0: User, 1: Order, 2: Product}
 */
function stockOrderPlacedIn(City $deliveryCity, Sector $sector, int $quantity = 2): array
{
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['unit_price' => 120, 'stock_quantity' => 10]);

    test()->actingAs($seller)
        ->post('/orders', [
            'customer_first_name' => 'Amina',
            'customer_last_name' => 'Bennani',
            'customer_phone' => '0611111111',
            'customer_address' => '5 rue des Fleurs',
            'city_id' => $deliveryCity->id,
            'sector_id' => $sector->id,
            'payment_method' => PaymentMethod::CASH->value,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ])
        ->assertSessionHasNoErrors();

    return [$seller, Order::acrossStores()->latest('id')->firstOrFail(), $product];
}

test('an order picked from stock waits to be packed instead of being collected', function () {
    [, $order] = stockOrderPlacedIn($this->faraway, $this->farawaySector);

    expect($order->status)->toBe(OrderStatus::AWAITING_PREPARATION)
        // Never offered to a courier: the parcel is already on our shelf.
        ->and($order->pickup_request_id)->toBeNull()
        // The depot is frozen on the order, so a later move of the shop's
        // warehouse cannot rewrite where this parcel shipped from.
        ->and($order->stock_hub_city_id)->toBe($this->hub->id);
});

test('an order without stock lines still enters the pickup flow', function () {
    $seller = StockFixtures::user(Role::SELLER);
    StockFixtures::store($seller);

    $this->actingAs($seller)
        ->post('/orders', [
            'customer_first_name' => 'Youssef',
            'customer_last_name' => 'Alami',
            'customer_phone' => '0622222222',
            'customer_address' => '8 avenue Hassan',
            'city_id' => $this->faraway->id,
            'sector_id' => $this->farawaySector->id,
            'payment_method' => PaymentMethod::CASH->value,
            'order_amount' => 300,
        ])
        ->assertSessionHasNoErrors();

    $order = Order::acrossStores()->latest('id')->firstOrFail();

    expect($order->status)->toBe(OrderStatus::CREATED)
        ->and($order->stock_hub_city_id)->toBeNull();
});

test('a vendor cannot declare his own order packed', function () {
    [$seller, $order] = stockOrderPlacedIn($this->faraway, $this->farawaySector);

    $this->actingAs($seller)
        ->post('/orders/preparation', ['ids' => [$order->id]])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::AWAITING_PREPARATION);
});

test('the depot ships a packed parcel straight out when the customer is in its own city', function () {
    $driver = preparationDriver($this->hubSector);
    [, $order] = stockOrderPlacedIn($this->hub, $this->hubSector);

    $this->actingAs(StockFixtures::user(Role::DISPATCHER))
        ->post('/orders/preparation', ['ids' => [$order->id]])
        ->assertSessionHasNoErrors();

    $order->refresh();

    // No transfer to wait for, so the parcel skips PREPARED's waiting room and
    // lands on a local courier's round.
    expect($order->status)->toBe(OrderStatus::IN_DELIVERY_CITY)
        ->and($order->driver_id)->toBe($driver->id)
        ->and($order->assigned_at)->not->toBeNull();

    // Both steps are journalled: the packing, then the automatic hand-off.
    $trail = $order->statusHistories()->pluck('status')->all();

    expect($trail)->toContain(OrderStatus::PREPARED)
        ->and($trail)->toContain(OrderStatus::IN_DELIVERY_CITY);
});

test('a courier covering the city is used when nobody covers the exact sector', function () {
    $cityDriver = preparationDriver($this->hubSector);

    $otherSector = Sector::query()->create([
        'city_id' => $this->hub->id,
        'name' => 'Uncovered Sector',
        'delivery_price' => 22.00,
        'is_active' => true,
    ]);

    [, $order] = stockOrderPlacedIn($this->hub, $otherSector);

    $this->actingAs(StockFixtures::user(Role::DISPATCHER))
        ->post('/orders/preparation', ['ids' => [$order->id]]);

    expect($order->fresh()->driver_id)->toBe($cityDriver->id);
});

test('a packed parcel bound for another city waits for a ride', function () {
    preparationDriver($this->farawaySector);
    [, $order] = stockOrderPlacedIn($this->faraway, $this->farawaySector);

    $this->actingAs(StockFixtures::user(Role::DISPATCHER))
        ->post('/orders/preparation', ['ids' => [$order->id]]);

    $order->refresh();

    // It has a journey left to make, so it stays on the shelf unassigned rather
    // than being handed to a courier in the wrong city.
    expect($order->status)->toBe(OrderStatus::PREPARED)
        ->and($order->driver_id)->toBeNull();
});

test('a packed parcel rides an inter-city manifest out of its depot', function () {
    [, $order] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    $dispatcher = StockFixtures::user(Role::DISPATCHER);

    $this->actingAs($dispatcher)->post('/orders/preparation', ['ids' => [$order->id]]);

    // Offered from the depot, not from the vendor's own city: the origin of a
    // stock parcel is where it was packed.
    $eligible = app(TransferService::class)->getEligibleOrders($this->hub->id, $this->faraway->id);

    expect($eligible->pluck('id')->all())->toContain($order->id);

    $transfer = app(TransferService::class)->create(
        StockFixtures::user(Role::ADMIN),
        $this->hub->id,
        $this->faraway->id,
        orderIds: [$order->id],
        contentType: TransferContentType::ORDERS,
    );

    expect($order->fresh()->status)->toBe(OrderStatus::TRANSFER_CREATED)
        ->and($transfer->orders()->pluck('orders.id')->all())->toBe([$order->id]);
});

test('a cancelled manifest puts a packed parcel back on its own shelf', function () {
    [, $order] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    $admin = StockFixtures::user(Role::ADMIN);

    $this->actingAs(StockFixtures::user(Role::DISPATCHER))->post('/orders/preparation', ['ids' => [$order->id]]);

    $transfer = app(TransferService::class)->create(
        $admin,
        $this->hub->id,
        $this->faraway->id,
        orderIds: [$order->id],
        contentType: TransferContentType::ORDERS,
    );

    app(TransferService::class)->applyStatus($transfer, TransferStatus::CANCELLED, $admin, 'Truck broke down.');

    // Back to PREPARED, not IN_DEPOT: the parcel was never collected from
    // anybody, and IN_DEPOT would offer it to a flow it does not belong to.
    expect($order->fresh()->status)->toBe(OrderStatus::PREPARED);

    expect(app(TransferService::class)->getEligibleOrders($this->hub->id, $this->faraway->id)->pluck('id')->all())
        ->toContain($order->id);
});

test('a packed parcel and a collected one share the same manifest', function () {
    [, $stockOrder] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    $admin = StockFixtures::user(Role::ADMIN);

    $this->actingAs(StockFixtures::user(Role::DISPATCHER))->post('/orders/preparation', ['ids' => [$stockOrder->id]]);

    // A plain parcel signed into the same depot, whose vendor lives in that city.
    $vendor = StockFixtures::user(Role::SELLER);
    $vendor->update(['city_id' => $this->hub->id]);

    $collected = Order::query()->create([
        'tracking_number' => 'COL-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $vendor->id,
        'customer_first_name' => 'Karim',
        'customer_last_name' => 'Idrissi',
        'customer_phone' => '0633333333',
        'customer_address' => '12 rue du Port',
        'city_id' => $this->faraway->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 220,
        'delivery_price' => 35,
        'status' => OrderStatus::IN_DEPOT->value,
    ]);

    $transfer = app(TransferService::class)->create(
        $admin,
        $this->hub->id,
        $this->faraway->id,
        orderIds: [$stockOrder->id, $collected->id],
        contentType: TransferContentType::ORDERS,
    );

    expect($transfer->orders()->pluck('orders.id')->sort()->values()->all())
        ->toBe(collect([$stockOrder->id, $collected->id])->sort()->values()->all());
});

test('the scanner vets a label before it joins the trolley', function () {
    [, $order] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    $dispatcher = StockFixtures::user(Role::DISPATCHER);

    $this->actingAs($dispatcher)
        ->postJson('/orders/preparation/scan', ['tracking_number' => $order->tracking_number])
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('order.tracking_number', $order->tracking_number)
        ->assertJsonPath('order.units', 2);

    $this->actingAs($dispatcher)
        ->postJson('/orders/preparation/scan', ['tracking_number' => 'SPD-2026-000000'])
        ->assertOk()
        ->assertJsonPath('valid', false);
});

test('a label already packed is refused by the scanner', function () {
    [, $order] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    $dispatcher = StockFixtures::user(Role::DISPATCHER);

    $this->actingAs($dispatcher)->post('/orders/preparation', ['ids' => [$order->id]]);

    $this->actingAs($dispatcher)
        ->postJson('/orders/preparation/scan', ['tracking_number' => $order->tracking_number])
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('order.status', OrderStatus::PREPARED->value);
});

test('a scanned trolley is packed in one go', function () {
    [, $local] = stockOrderPlacedIn($this->hub, $this->hubSector);
    [, $travelling] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    preparationDriver($this->hubSector);

    $this->actingAs(StockFixtures::user(Role::DISPATCHER))
        ->postJson('/orders/preparation/bulk-scan', [
            'orders' => [$local->tracking_number, $travelling->tracking_number],
        ])
        ->assertOk()
        ->assertJsonPath('prepared', 2)
        ->assertJsonPath('skipped', 0);

    // Same button, two destinies — decided by the depot, not by the agent.
    expect($local->fresh()->status)->toBe(OrderStatus::IN_DELIVERY_CITY)
        ->and($travelling->fresh()->status)->toBe(OrderStatus::PREPARED);
});

test('a trolley holding an unknown label is refused whole', function () {
    [, $order] = stockOrderPlacedIn($this->faraway, $this->farawaySector);

    $this->actingAs(StockFixtures::user(Role::DISPATCHER))
        ->postJson('/orders/preparation/bulk-scan', [
            'orders' => [$order->tracking_number, 'SPD-2026-000000'],
        ])
        ->assertStatus(422);

    // Nothing was packed: the trolley and the screen disagree, and packing the
    // rest would hide that from the agent.
    expect($order->fresh()->status)->toBe(OrderStatus::AWAITING_PREPARATION);
});

test('an order already packed by a colleague is skipped, not failed', function () {
    [, $first] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    [, $second] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    $dispatcher = StockFixtures::user(Role::DISPATCHER);

    $this->actingAs($dispatcher)->post('/orders/preparation', ['ids' => [$first->id]]);

    $this->actingAs($dispatcher)
        ->post('/orders/preparation', ['ids' => [$first->id, $second->id]])
        ->assertSessionHasNoErrors();

    expect($second->fresh()->status)->toBe(OrderStatus::PREPARED);
});

test('the queue holds every shop and only the parcels awaiting a packer', function () {
    [, $waiting] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    [, $packed] = stockOrderPlacedIn($this->faraway, $this->farawaySector);
    $dispatcher = StockFixtures::user(Role::DISPATCHER);

    $this->actingAs($dispatcher)->post('/orders/preparation', ['ids' => [$packed->id]]);

    $this->actingAs($dispatcher)
        ->get('/orders/preparation')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('orders/preparation/index')
            ->where('orders.data.0.tracking_number', $waiting->tracking_number)
            ->where('orders.meta.total', 1));
});

test('the picking bench is closed to anybody without the grant', function () {
    $this->actingAs(StockFixtures::user(Role::SELLER))
        ->get('/orders/preparation')
        ->assertForbidden();
});
