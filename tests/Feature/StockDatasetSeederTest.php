<?php

use App\Enums\OrderStatus;
use App\Enums\StockMovementSource;
use App\Enums\StockReceptionStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockReception;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CitySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\StockDatasetSeeder;

/**
 * A smoke test over the fulfilment demo dataset.
 *
 * The seeder writes everything through the real services, which is what makes it
 * worth testing: if it still runs, then the invariants those services enforce —
 * a shelf that matches its own ledger, a total that matches its lines, a depot
 * that agrees with the shipments addressed to it — all still hold.
 */
beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
        CitySeeder::class,
        SectorSeeder::class,
    ]);

    // The back office the seeder leans on to count shipments in and pack orders.
    $admin = Role::query()->where('name', Role::ADMIN)->firstOrFail();
    $user = User::factory()->create(['role_id' => $admin->id]);
    $user->roles()->sync([$admin->id]);

    $this->seed(StockDatasetSeeder::class);
});

test('the seeder gives both demo shops a depot in a hub city', function () {
    $stores = Store::query()
        ->whereHas('owner', fn ($query) => $query->whereIn('email', [
            'stock1@speedzone.ma',
            'stock2@speedzone.ma',
        ]))
        ->with('stockHubCity')
        ->get();

    expect($stores)->toHaveCount(2);

    foreach ($stores as $store) {
        expect($store->stock_hub_city_id)->not->toBeNull()
            ->and($store->stockHubCity->is_stock_hub)->toBeTrue();
    }

    // One shop warehouses away from home, which is the case that catches code
    // reading the vendor's city where it should read the depot.
    $awayFromHome = $stores->first(fn (Store $store) => $store->city_id !== $store->stock_hub_city_id);

    expect($awayFromHome)->not->toBeNull();
});

test('every shipment is addressed to the depot of its own shop', function () {
    $receptions = StockReception::acrossStores()->with('store')->get();

    expect($receptions)->not->toBeEmpty();

    foreach ($receptions as $reception) {
        expect($reception->destination_city_id)->toBe($reception->store->stock_hub_city_id);
    }
});

test('the demo freezes a shipment at every stage of the journey', function () {
    $counts = StockReception::acrossStores()
        ->selectRaw('status, count(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

    // No screen of the module should open empty on a fresh install: each stage has
    // somebody's work waiting on it.
    foreach (StockReceptionStatus::cases() as $status) {
        if ($status === StockReceptionStatus::CANCELLED) {
            continue;
        }

        expect($counts[$status->value] ?? 0)
            ->toBeGreaterThan(0, "no demo shipment sits in {$status->value}");
    }
});

test('the journal of every shipment matches where it stands and reads forwards', function () {
    $receptions = StockReception::acrossStores()
        ->with(['statusHistories' => fn ($query) => $query->orderBy('id')])
        ->get();

    expect($receptions)->not->toBeEmpty();

    foreach ($receptions as $reception) {
        $trail = $reception->statusHistories;

        expect($trail)->not->toBeEmpty()
            ->and($trail->last()->new_status)->toBe($reception->statusEnum());

        $previous = null;

        foreach ($trail as $entry) {
            // A hand-over dated before the one it followed would make the timeline
            // unreadable, and the backdating in the seeder is exactly where that can
            // slip.
            expect($entry->changed_by)->not->toBeNull()
                ->and($entry->old_status)->toBe($previous?->new_status);

            if ($previous) {
                expect($entry->created_at->greaterThanOrEqualTo($previous->created_at))->toBeTrue();
            }

            $previous = $entry;
        }
    }
});

test('replaying the ledger reproduces every shelf', function () {
    $products = Product::acrossStores()->get();

    expect($products)->not->toBeEmpty();

    foreach ($products as $product) {
        $replayed = (int) StockAdjustment::acrossStores()->where('product_id', $product->id)->sum('delta');

        expect((int) $product->stock_quantity)->toBe($replayed);
    }
});

test('orders picked from stock skip the pickup and carry their depot', function () {
    $stockOrders = Order::acrossStores()->has('items')->with('store')->get();

    expect($stockOrders)->not->toBeEmpty();

    foreach ($stockOrders as $order) {
        expect($order->pickup_request_id)->toBeNull()
            ->and($order->stock_hub_city_id)->toBe($order->store->stock_hub_city_id);
    }
});

test('the demo shows the queue and both halves of the packing decision', function () {
    $byStatus = Order::acrossStores()
        ->has('items')
        ->get()
        ->groupBy(fn (Order $order) => $order->status->value);

    // Something left for a packer to do, so the bench is not an empty screen.
    expect($byStatus->get(OrderStatus::AWAITING_PREPARATION->value))->not->toBeNull();

    $localOrTravelling = collect([
        // Packed in the customer's city: gone straight out on a local round.
        OrderStatus::IN_DELIVERY_CITY->value,
        // Packed elsewhere: waiting for a transfer next to the collected parcels.
        OrderStatus::PREPARED->value,
    ])->filter(fn (string $status) => $byStatus->has($status));

    expect($localOrTravelling)->not->toBeEmpty();
});

test('a parcel sent straight to delivery got a courier of that city', function () {
    $shipped = Order::acrossStores()
        ->where('status', OrderStatus::IN_DELIVERY_CITY->value)
        ->has('items')
        ->with('driver.sectors')
        ->get();

    // Whether any parcel was sold in its own depot city is up to the random
    // basket, so an empty list is a legitimate run rather than a failure.
    foreach ($shipped as $order) {
        expect($order->stock_hub_city_id)->toBe($order->city_id);

        // A city with no courier at all leaves the parcel unassigned, which is
        // correct behaviour rather than a seeding failure.
        if ($order->driver === null) {
            continue;
        }

        expect($order->driver->sectors->pluck('city_id')->all())->toContain($order->city_id);
    }
});

test('a second run neither duplicates the catalog nor drains the shelves again', function () {
    $before = [
        'products' => Product::acrossStores()->count(),
        'receptions' => StockReception::acrossStores()->count(),
        'orders' => Order::acrossStores()->has('items')->count(),
        'movements' => StockAdjustment::acrossStores()->count(),
        'quantity' => (int) Product::acrossStores()->sum('stock_quantity'),
    ];

    $this->seed(StockDatasetSeeder::class);

    expect(Product::acrossStores()->count())->toBe($before['products'])
        ->and(StockReception::acrossStores()->count())->toBe($before['receptions'])
        ->and(Order::acrossStores()->has('items')->count())->toBe($before['orders'])
        ->and(StockAdjustment::acrossStores()->count())->toBe($before['movements'])
        ->and((int) Product::acrossStores()->sum('stock_quantity'))->toBe($before['quantity']);
});

test('the manual corrections carry a reason and an author', function () {
    $corrections = StockAdjustment::acrossStores()->forSource(StockMovementSource::MANUAL)->get();

    expect($corrections)->not->toBeEmpty();

    foreach ($corrections as $correction) {
        expect($correction->reason)->not->toBeNull()
            ->and($correction->user_id)->not->toBeNull()
            ->and($correction->delta)->not->toBe(0);
    }
});

test('the hub cities the seeder needs are flagged by the city seeder', function () {
    expect(City::query()->stockHub()->where('is_active', true)->count())->toBeGreaterThanOrEqual(1);
});
