<?php

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementSource;
use App\Enums\StockReceptionStatus;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockReception;
use App\Services\StockReceptionService;
use App\Support\StoreContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\StockFixtures;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

test('a vendor declares a shipment as a draft then asks for it to be collected', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $this->actingAs($seller)
        ->post('/stock-receptions', [
            'status' => StockReceptionStatus::DRAFT->value,
            'sent_at' => now()->toDateString(),
            'sending_notes' => '2 sealed boxes',
            'items' => [['product_id' => $product->id, 'quantity_sent' => 40]],
        ])
        ->assertSessionHasNoErrors();

    $reception = StockReception::acrossStores()->latest('id')->firstOrFail();

    expect($reception->statusEnum())->toBe(StockReceptionStatus::DRAFT)
        ->and($reception->reference)->toStartWith('RCP-')
        ->and($reception->totalSent())->toBe(40)
        // Declaring a shipment credits nothing: only the depot's count does.
        ->and($product->fresh()->stock_quantity)->toBe(0);

    $this->actingAs($seller)
        ->put("/stock-receptions/{$reception->id}/send")
        ->assertSessionHasNoErrors();

    expect($reception->fresh()->statusEnum())->toBe(StockReceptionStatus::AWAITING_PICKUP)
        ->and($product->fresh()->stock_quantity)->toBe(0);
});

test('a shipment can be declared from an unguarded context', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    app(StoreContext::class)->enforceFor($seller, $store->id);

    // Seeders and console commands run with mass-assignment protection lifted, so
    // the service must drop the line list itself instead of trusting $fillable to
    // keep `items` out of the shipment row.
    $reception = Model::unguarded(fn () => app(StockReceptionService::class)->create([
        'status' => StockReceptionStatus::AWAITING_PICKUP->value,
        'sent_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'quantity_sent' => 12]],
    ], $seller));

    expect($reception->totalSent())->toBe(12)
        ->and($reception->items)->toHaveCount(1);
});

test('a shipment queued for collection is no longer editable by the vendor', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $this->actingAs($seller)->post('/stock-receptions', [
        'status' => StockReceptionStatus::AWAITING_PICKUP->value,
        'items' => [['product_id' => $product->id, 'quantity_sent' => 10]],
    ]);

    $reception = StockReception::acrossStores()->latest('id')->firstOrFail();

    $this->actingAs($seller)
        ->put("/stock-receptions/{$reception->id}", [
            'items' => [['product_id' => $product->id, 'quantity_sent' => 999]],
        ])
        ->assertForbidden();

    expect($reception->fresh()->totalSent())->toBe(10);
});

test('a vendor cannot count his own shipment in', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $this->actingAs($seller)->post('/stock-receptions', [
        'status' => StockReceptionStatus::AWAITING_PICKUP->value,
        'items' => [['product_id' => $product->id, 'quantity_sent' => 10]],
    ]);

    $reception = StockFixtures::readyForDepot(
        StockReception::acrossStores()->latest('id')->firstOrFail()
    );
    $line = $reception->items()->firstOrFail();

    $this->actingAs($seller)
        ->put("/stock-receptions/{$reception->id}/validate", [
            'items' => [['id' => $line->id, 'quantity_received' => 10]],
        ])
        ->assertForbidden();

    expect($product->fresh()->stock_quantity)->toBe(0);
});

test('the depot credits what it counted and not what was declared', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $this->actingAs($seller)->post('/stock-receptions', [
        'status' => StockReceptionStatus::AWAITING_PICKUP->value,
        'items' => [['product_id' => $product->id, 'quantity_sent' => 50]],
    ]);

    $reception = StockFixtures::readyForDepot(
        StockReception::acrossStores()->latest('id')->firstOrFail()
    );
    $line = $reception->items()->firstOrFail();

    $agent = StockFixtures::user(Role::DISPATCHER);

    $this->actingAs($agent)
        ->put("/stock-receptions/{$reception->id}/validate", [
            'received_at' => now()->toDateString(),
            'reception_notes' => '3 items damaged in transit',
            'items' => [[
                'id' => $line->id,
                'quantity_received' => 47,
                'quantity_rejected' => 3,
            ]],
        ])
        ->assertSessionHasNoErrors();

    $reception->refresh();

    expect($reception->statusEnum())->toBe(StockReceptionStatus::VALIDATED)
        ->and($reception->received_by)->toBe($agent->id)
        ->and($product->fresh()->stock_quantity)->toBe(47);

    $movement = StockAdjustment::acrossStores()->where('product_id', $product->id)->firstOrFail();

    expect($movement->source)->toBe(StockMovementSource::RECEPTION)
        ->and($movement->delta)->toBe(47)
        ->and($movement->stock_before)->toBe(0)
        ->and($movement->stock_after)->toBe(47)
        ->and($movement->stock_reception_id)->toBe($reception->id)
        ->and($movement->user_id)->toBe($agent->id);
});

test('a shipment cannot be counted in twice', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $this->actingAs($seller)->post('/stock-receptions', [
        'status' => StockReceptionStatus::AWAITING_PICKUP->value,
        'items' => [['product_id' => $product->id, 'quantity_sent' => 20]],
    ]);

    $reception = StockFixtures::readyForDepot(
        StockReception::acrossStores()->latest('id')->firstOrFail()
    );
    $line = $reception->items()->firstOrFail();
    $agent = StockFixtures::user(Role::DISPATCHER);
    $payload = ['items' => [['id' => $line->id, 'quantity_received' => 20]]];

    $this->actingAs($agent)->put("/stock-receptions/{$reception->id}/validate", $payload);

    // Closed documents are out of the depot's reach, so the second attempt never
    // gets as far as the ledger.
    $this->actingAs($agent)->put("/stock-receptions/{$reception->id}/validate", $payload)->assertForbidden();

    expect($product->fresh()->stock_quantity)->toBe(20)
        ->and(StockAdjustment::acrossStores()->where('product_id', $product->id)->count())->toBe(1);
});

test('a line belonging to another shipment is never credited', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $productA = StockFixtures::product($seller, $store, ['sku' => 'AAA-001']);
    $productB = StockFixtures::product($seller, $store, ['sku' => 'BBB-001']);

    $this->actingAs($seller)->post('/stock-receptions', [
        'status' => StockReceptionStatus::AWAITING_PICKUP->value,
        'items' => [['product_id' => $productA->id, 'quantity_sent' => 5]],
    ]);
    $first = StockFixtures::readyForDepot(
        StockReception::acrossStores()->latest('id')->firstOrFail()
    );

    $this->actingAs($seller)->post('/stock-receptions', [
        'status' => StockReceptionStatus::AWAITING_PICKUP->value,
        'items' => [['product_id' => $productB->id, 'quantity_sent' => 5]],
    ]);
    $second = StockReception::acrossStores()->latest('id')->firstOrFail();

    $foreignLine = $second->items()->firstOrFail();

    $this->actingAs(StockFixtures::user(Role::DISPATCHER))
        ->put("/stock-receptions/{$first->id}/validate", [
            'items' => [['id' => $foreignLine->id, 'quantity_received' => 5]],
        ])
        ->assertSessionHasErrors('items.0.id');

    expect($productB->fresh()->stock_quantity)->toBe(0);
});

test('a mass inventory correction demands a reason and journals the gap', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['stock_quantity' => 30]);

    $this->actingAs($seller)
        ->post('/stock/inventory', [
            'adjustments' => [['product_id' => $product->id, 'counted_quantity' => 24]],
        ])
        ->assertSessionHasErrors('adjustments.0.reason');

    expect($product->fresh()->stock_quantity)->toBe(30);

    $this->actingAs($seller)
        ->post('/stock/inventory', [
            'adjustments' => [[
                'product_id' => $product->id,
                'counted_quantity' => 24,
                'reason' => StockAdjustmentReason::THEFT_OR_LOSS->value,
                'note' => 'Six units missing from the shelf',
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->stock_quantity)->toBe(24);

    $movement = StockAdjustment::acrossStores()->where('product_id', $product->id)->firstOrFail();

    expect($movement->source)->toBe(StockMovementSource::MANUAL)
        ->and($movement->reason)->toBe(StockAdjustmentReason::THEFT_OR_LOSS)
        ->and($movement->delta)->toBe(-6)
        ->and($movement->stock_before)->toBe(30)
        ->and($movement->stock_after)->toBe(24)
        ->and($movement->user_id)->toBe($seller->id);
});

test('counting a product at its recorded quantity journals nothing', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['stock_quantity' => 12]);

    $this->actingAs($seller)
        ->post('/stock/inventory', [
            'adjustments' => [['product_id' => $product->id, 'counted_quantity' => 12]],
        ])
        ->assertSessionHasNoErrors();

    expect(StockAdjustment::acrossStores()->where('product_id', $product->id)->count())->toBe(0);
});

test('a ledger entry can never be rewritten', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store, ['stock_quantity' => 5]);

    $this->actingAs($seller)->post('/stock/inventory', [
        'adjustments' => [[
            'product_id' => $product->id,
            'counted_quantity' => 4,
            'reason' => StockAdjustmentReason::DAMAGED->value,
        ]],
    ]);

    $movement = StockAdjustment::acrossStores()->where('product_id', $product->id)->firstOrFail();

    expect(fn () => $movement->update(['delta' => 100]))->toThrow(RuntimeException::class)
        ->and(fn () => $movement->delete())->toThrow(RuntimeException::class);
});

test('a vendor cannot correct the stock of another shop', function () {
    $seller = StockFixtures::user(Role::SELLER);
    StockFixtures::store($seller);

    $intruder = StockFixtures::user(Role::SELLER);
    $foreignStore = StockFixtures::store($intruder, 'Foreign shop');
    $foreignProduct = StockFixtures::product($intruder, $foreignStore, ['stock_quantity' => 100]);

    $this->actingAs($seller)
        ->post('/stock/inventory', [
            'adjustments' => [[
                'product_id' => $foreignProduct->id,
                'counted_quantity' => 0,
                'reason' => StockAdjustmentReason::COUNT_ERROR->value,
            ]],
        ])
        ->assertSessionHasErrors('adjustments.0.product_id');

    expect($foreignProduct->fresh()->stock_quantity)->toBe(100);
});
