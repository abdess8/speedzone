<?php

use App\Enums\NotificationType;
use App\Enums\StockReceptionStatus;
use App\Models\Role;
use App\Notifications\StockPickupRequestedNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Support\StockFixtures;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

test('the shipment is journalled at every hand-over from the shop counter to the shelf', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $reception = StockFixtures::awaitingPickup($this, $seller, [
        ['product_id' => $product->id, 'quantity_sent' => 30],
    ]);
    $line = $reception->items()->firstOrFail();

    $collector = StockFixtures::collector();

    $this->actingAs($collector)
        ->put("/stock-receptions/{$reception->id}/collect", [
            'collection_notes' => 'Two units missing from the shop shelf',
            'items' => [['id' => $line->id, 'quantity_collected' => 28]],
        ])
        ->assertSessionHasNoErrors();

    $reception->refresh();

    expect($reception->statusEnum())->toBe(StockReceptionStatus::COLLECTED)
        ->and($reception->collected_by)->toBe($collector->id)
        ->and($reception->collected_at)->not->toBeNull()
        ->and($line->fresh()->quantity_collected)->toBe(28)
        // Goods in a van are not stock: nothing is credited before the depot signs.
        ->and($product->fresh()->stock_quantity)->toBe(0);

    $this->actingAs($collector)
        ->put("/stock-receptions/{$reception->id}/dispatch")
        ->assertSessionHasNoErrors();

    expect($reception->fresh()->statusEnum())->toBe(StockReceptionStatus::IN_TRANSIT);

    $agent = StockFixtures::user(Role::DISPATCHER);

    $this->actingAs($agent)
        ->put("/stock-receptions/{$reception->id}/validate", [
            'items' => [['id' => $line->id, 'quantity_received' => 27, 'quantity_rejected' => 1]],
        ])
        ->assertSessionHasNoErrors();

    expect($reception->fresh()->statusEnum())->toBe(StockReceptionStatus::VALIDATED)
        ->and($product->fresh()->stock_quantity)->toBe(27);

    $trail = $reception->statusHistories()->orderBy('id')->get();

    expect($trail->pluck('new_status')->all())->toBe([
        StockReceptionStatus::AWAITING_PICKUP,
        StockReceptionStatus::COLLECTED,
        StockReceptionStatus::IN_TRANSIT,
        StockReceptionStatus::VALIDATED,
    ])
        // Who did what is the whole point of the journal: a shortfall has to be
        // traceable to the pair of hands that reported it.
        ->and($trail->pluck('changed_by')->all())->toBe([
            $seller->id,
            $collector->id,
            $collector->id,
            $agent->id,
        ])
        ->and($trail[1]->comment)->toContain('28');
});

test('only the collectors working the shop city are sent for the parcel', function () {
    Notification::fake();

    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $local = StockFixtures::collector();
    $distant = StockFixtures::collector(StockFixtures::farCity());

    StockFixtures::awaitingPickup($this, $seller, [['product_id' => $product->id, 'quantity_sent' => 10]]);

    Notification::assertSentTo($local, StockPickupRequestedNotification::class);
    Notification::assertNotSentTo($distant, StockPickupRequestedNotification::class);
    Notification::assertNotSentTo($seller, StockPickupRequestedNotification::class);
});

test('the pickup notification carries the round a collector needs to plan it', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller, 'Atlas Bazaar');
    $product = StockFixtures::product($seller, $store);

    $collector = StockFixtures::collector();

    StockFixtures::awaitingPickup($this, $seller, [['product_id' => $product->id, 'quantity_sent' => 14]]);

    $payload = $collector->notifications()->firstOrFail()->data;

    expect($payload['type'])->toBe(NotificationType::StockPickupRequested->value)
        ->and($payload['units'])->toBe(14)
        ->and($payload['pickup_city'])->toBe(StockFixtures::shopCity()->name)
        ->and($payload['destination_city'])->toBe(StockFixtures::hubCity()->name)
        ->and($payload['message'])->toContain('Atlas Bazaar');
});

test('a collector cannot drive to a shop outside the cities he works', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $reception = StockFixtures::awaitingPickup($this, $seller, [
        ['product_id' => $product->id, 'quantity_sent' => 10],
    ]);
    $line = $reception->items()->firstOrFail();

    $distant = StockFixtures::collector(StockFixtures::farCity());

    $this->actingAs($distant)
        ->put("/stock-receptions/{$reception->id}/collect", [
            'items' => [['id' => $line->id, 'quantity_collected' => 10]],
        ])
        ->assertForbidden();

    expect($reception->fresh()->statusEnum())->toBe(StockReceptionStatus::AWAITING_PICKUP);
});

test('a vendor cannot sign for his own hand-over', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $reception = StockFixtures::awaitingPickup($this, $seller, [
        ['product_id' => $product->id, 'quantity_sent' => 10],
    ]);
    $line = $reception->items()->firstOrFail();

    // The declaration and the count of it have to come from two different pairs of
    // hands, otherwise comparing them proves nothing.
    $this->actingAs($seller)
        ->put("/stock-receptions/{$reception->id}/collect", [
            'items' => [['id' => $line->id, 'quantity_collected' => 10]],
        ])
        ->assertForbidden();

    expect($line->fresh()->quantity_collected)->toBeNull();
});

test('a shipment cannot be collected twice', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $reception = StockFixtures::awaitingPickup($this, $seller, [
        ['product_id' => $product->id, 'quantity_sent' => 10],
    ]);
    $line = $reception->items()->firstOrFail();
    $collector = StockFixtures::collector();

    $this->actingAs($collector)->put("/stock-receptions/{$reception->id}/collect", [
        'items' => [['id' => $line->id, 'quantity_collected' => 9]],
    ]);

    $this->actingAs($collector)
        ->put("/stock-receptions/{$reception->id}/collect", [
            'items' => [['id' => $line->id, 'quantity_collected' => 10]],
        ])
        ->assertForbidden();

    expect($line->fresh()->quantity_collected)->toBe(9);
});

test('only the collector holding the parcel may put it on the road', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $reception = StockFixtures::awaitingPickup($this, $seller, [
        ['product_id' => $product->id, 'quantity_sent' => 10],
    ]);
    $line = $reception->items()->firstOrFail();

    $collector = StockFixtures::collector();
    $colleague = StockFixtures::collector();

    $this->actingAs($collector)->put("/stock-receptions/{$reception->id}/collect", [
        'items' => [['id' => $line->id, 'quantity_collected' => 10]],
    ]);

    // Same city, same grant, but not the person with the boxes in his van: only he
    // can state that they left.
    $this->actingAs($colleague)
        ->put("/stock-receptions/{$reception->id}/dispatch")
        ->assertForbidden();

    expect($reception->fresh()->statusEnum())->toBe(StockReceptionStatus::COLLECTED);
});

test('the depot cannot count in a parcel that has not left the shop', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $reception = StockFixtures::awaitingPickup($this, $seller, [
        ['product_id' => $product->id, 'quantity_sent' => 10],
    ]);
    $line = $reception->items()->firstOrFail();
    $agent = StockFixtures::user(Role::DISPATCHER);
    $payload = ['items' => [['id' => $line->id, 'quantity_received' => 10]]];

    $this->actingAs($agent)
        ->put("/stock-receptions/{$reception->id}/validate", $payload)
        ->assertForbidden();

    // Nor once a collector holds it: the goods are still in a van, not at the door.
    $collector = StockFixtures::collector();

    $this->actingAs($collector)->put("/stock-receptions/{$reception->id}/collect", [
        'items' => [['id' => $line->id, 'quantity_collected' => 10]],
    ]);

    $this->actingAs($agent)
        ->put("/stock-receptions/{$reception->id}/validate", $payload)
        ->assertForbidden();

    expect($product->fresh()->stock_quantity)->toBe(0);
});

test('an agent only closes the shipments addressed to his own depot', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $reception = StockFixtures::readyForDepot(
        StockFixtures::awaitingPickup($this, $seller, [['product_id' => $product->id, 'quantity_sent' => 10]])
    );
    $line = $reception->items()->firstOrFail();
    $payload = ['items' => [['id' => $line->id, 'quantity_received' => 10]]];

    $elsewhere = StockFixtures::user(Role::DISPATCHER);
    $elsewhere->forceFill(['city_id' => StockFixtures::shopCity()->id])->save();

    $this->actingAs($elsewhere->fresh(['roles.permissions']))
        ->put("/stock-receptions/{$reception->id}/validate", $payload)
        ->assertForbidden();

    expect($product->fresh()->stock_quantity)->toBe(0);

    // Somebody has to be able to reach across a city when a parcel is misrouted.
    $admin = StockFixtures::user(Role::ADMIN);
    $admin->forceFill(['city_id' => StockFixtures::shopCity()->id])->save();

    $this->actingAs($admin->fresh(['roles.permissions']))
        ->put("/stock-receptions/{$reception->id}/validate", $payload)
        ->assertSessionHasNoErrors();

    expect($product->fresh()->stock_quantity)->toBe(10);
});

test('an unanswered line is written off against what the collector loaded', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $kept = StockFixtures::product($seller, $store, ['sku' => 'KEPT-001']);
    $lost = StockFixtures::product($seller, $store, ['sku' => 'LOST-001']);

    $reception = StockFixtures::awaitingPickup($this, $seller, [
        ['product_id' => $kept->id, 'quantity_sent' => 10],
        ['product_id' => $lost->id, 'quantity_sent' => 30],
    ]);

    $keptLine = $reception->items()->where('product_id', $kept->id)->firstOrFail();
    $lostLine = $reception->items()->where('product_id', $lost->id)->firstOrFail();

    $collector = StockFixtures::collector();

    $this->actingAs($collector)->put("/stock-receptions/{$reception->id}/collect", [
        'items' => [
            ['id' => $keptLine->id, 'quantity_collected' => 10],
            ['id' => $lostLine->id, 'quantity_collected' => 20],
        ],
    ]);

    $this->actingAs($collector)->put("/stock-receptions/{$reception->id}/dispatch");

    $this->actingAs(StockFixtures::user(Role::DISPATCHER))
        ->put("/stock-receptions/{$reception->id}/validate", [
            'items' => [['id' => $keptLine->id, 'quantity_received' => 10]],
        ])
        ->assertSessionHasNoErrors();

    // The ten units the vendor declared but never handed over are already accounted
    // for at the shop door; charging the road for them too would count them twice.
    expect($lostLine->fresh()->quantity_rejected)->toBe(20)
        ->and($lostLine->fresh()->quantity_received)->toBe(0)
        ->and($lost->fresh()->stock_quantity)->toBe(0)
        ->and($kept->fresh()->stock_quantity)->toBe(10);
});

test('a collector who took the parcel can still call the round off', function () {
    $seller = StockFixtures::user(Role::SELLER);
    $store = StockFixtures::store($seller);
    $product = StockFixtures::product($seller, $store);

    $reception = StockFixtures::awaitingPickup($this, $seller, [
        ['product_id' => $product->id, 'quantity_sent' => 10],
    ]);
    $line = $reception->items()->firstOrFail();
    $collector = StockFixtures::collector();

    $this->actingAs($collector)->put("/stock-receptions/{$reception->id}/collect", [
        'items' => [['id' => $line->id, 'quantity_collected' => 10]],
    ]);

    // The vendor no longer can: the boxes left his counter, and erasing the
    // hand-over would erase something that physically happened.
    $this->actingAs($seller)
        ->put("/stock-receptions/{$reception->id}/cancel")
        ->assertForbidden();

    $this->actingAs($collector)
        ->put("/stock-receptions/{$reception->id}/cancel", ['reason' => 'Vendor withdrew the parcel'])
        ->assertSessionHasNoErrors();

    expect($reception->fresh()->statusEnum())->toBe(StockReceptionStatus::CANCELLED)
        ->and($product->fresh()->stock_quantity)->toBe(0);
});
