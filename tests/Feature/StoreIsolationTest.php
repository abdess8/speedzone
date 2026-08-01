<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Middleware\ResolveActiveStore;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function storeTestUser(string $roleName, ?User $parent = null): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();

    $user = User::factory()->create([
        'role_id' => $role->id,
        'parent_user_id' => $parent?->id,
    ]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function storeTestStore(User $owner, string $name, array $members = []): Store
{
    $store = Store::query()->create([
        'owner_id' => $owner->id,
        'name' => $name,
        'is_default' => ! Store::query()->where('owner_id', $owner->id)->exists(),
        'is_active' => true,
    ]);

    $store->users()->syncWithoutDetaching(array_merge([$owner->id], $members));

    return $store;
}

function storeTestCity(): City
{
    return City::query()->create([
        'name' => 'Store City',
        'code' => 'STC',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function storeTestSector(City $city): Sector
{
    return Sector::query()->create([
        'city_id' => $city->id,
        'name' => 'Store Sector',
        'delivery_price' => 25.00,
        'is_active' => true,
    ]);
}

function storeTestOrder(User $seller, Store $store, City $city, Sector $sector): Order
{
    // Written through the unscoped query on purpose: the fixture must be able
    // to plant an order in a store the actor is not standing on.
    $order = Order::acrossStores()->create([
        'tracking_number' => 'STR-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'store_id' => $store->id,
        'customer_first_name' => 'John',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000000',
        'customer_address' => '123 Test Street',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 100,
        'delivery_price' => 25,
        'status' => OrderStatus::CREATED->value,
    ]);

    return $order->fresh();
}

test('a seller only sees the orders of the store he is standing on', function () {
    $seller = storeTestUser(Role::SELLER);
    $storeA = storeTestStore($seller, 'Store A');
    $storeB = storeTestStore($seller, 'Store B');
    $city = storeTestCity();
    $sector = storeTestSector($city);

    $orderA = storeTestOrder($seller, $storeA, $city, $sector);
    $orderB = storeTestOrder($seller, $storeB, $city, $sector);

    $response = $this->actingAs($seller)
        ->withSession([ResolveActiveStore::SESSION_KEY => $storeA->id])
        ->get('/orders');

    $response->assertOk();

    $trackingNumbers = collect(
        $response->viewData('page')['props']['orders']['data'] ?? []
    )->pluck('tracking_number');

    expect($trackingNumbers)->toContain($orderA->tracking_number)
        ->not->toContain($orderB->tracking_number);
});

test('an order from another store is unreachable by direct url', function () {
    $seller = storeTestUser(Role::SELLER);
    $storeA = storeTestStore($seller, 'Store A');
    $storeB = storeTestStore($seller, 'Store B');
    $city = storeTestCity();
    $sector = storeTestSector($city);

    $orderB = storeTestOrder($seller, $storeB, $city, $sector);

    // 404 rather than 403: the store scope runs at route model binding, so the
    // row does not exist as far as this request is concerned.
    $this->actingAs($seller)
        ->withSession([ResolveActiveStore::SESSION_KEY => $storeA->id])
        ->get("/orders/{$orderB->id}")
        ->assertNotFound();
});

test('an order created while standing on a store is attributed to it', function () {
    $seller = storeTestUser(Role::SELLER);
    storeTestStore($seller, 'Store A');
    $storeB = storeTestStore($seller, 'Store B');
    $city = storeTestCity();
    $sector = storeTestSector($city);

    $this->actingAs($seller)
        ->withSession([ResolveActiveStore::SESSION_KEY => $storeB->id])
        ->post('/orders', [
            'customer_first_name' => 'Amina',
            'customer_last_name' => 'Bennani',
            'customer_phone' => '0611111111',
            'customer_address' => '5 rue des Fleurs',
            'city_id' => $city->id,
            'sector_id' => $sector->id,
            'payment_method' => PaymentMethod::CASH->value,
            'order_amount' => 250,
        ])
        ->assertSessionHasNoErrors();

    $order = Order::acrossStores()->latest('id')->first();

    expect($order->store_id)->toBe($storeB->id);
});

test('a bulk import attributes every row to the active store', function () {
    $seller = storeTestUser(Role::SELLER);
    storeTestStore($seller, 'Store A');
    $storeB = storeTestStore($seller, 'Store B');
    $city = storeTestCity();
    $sector = storeTestSector($city);

    $row = fn (string $phone) => [
        'customer_first_name' => 'Client',
        'customer_last_name' => 'Import',
        'customer_phone' => $phone,
        'customer_address' => '12 avenue Hassan II',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 199,
    ];

    $this->actingAs($seller)
        ->withSession([ResolveActiveStore::SESSION_KEY => $storeB->id])
        ->post('/orders/import', ['orders' => [$row('0622222222'), $row('0633333333')]])
        ->assertSessionHasNoErrors();

    expect(Order::acrossStores()->where('store_id', $storeB->id)->count())->toBe(2);
});

test('a team member reads his employer orders but only in the stores granted to him', function () {
    $seller = storeTestUser(Role::SELLER);
    $storeA = storeTestStore($seller, 'Store A');
    $storeB = storeTestStore($seller, 'Store B');
    $city = storeTestCity();
    $sector = storeTestSector($city);

    $member = storeTestUser(Role::SELLER, parent: $seller);
    $storeA->users()->syncWithoutDetaching([$member->id]);

    $orderA = storeTestOrder($seller, $storeA, $city, $sector);
    storeTestOrder($seller, $storeB, $city, $sector);

    $response = $this->actingAs($member)
        ->withSession([ResolveActiveStore::SESSION_KEY => $storeA->id])
        ->get('/orders');

    $response->assertOk();

    $orders = collect($response->viewData('page')['props']['orders']['data'] ?? []);

    expect($orders)->toHaveCount(1)
        ->and($orders->first()['tracking_number'])->toBe($orderA->tracking_number);
});

test('a forged store id in session is ignored in favour of the default store', function () {
    $seller = storeTestUser(Role::SELLER);
    $storeA = storeTestStore($seller, 'Store A');
    $city = storeTestCity();
    $sector = storeTestSector($city);

    $intruder = storeTestUser(Role::SELLER);
    $foreignStore = storeTestStore($intruder, 'Foreign Store');
    $foreignOrder = storeTestOrder($intruder, $foreignStore, $city, $sector);

    $ownOrder = storeTestOrder($seller, $storeA, $city, $sector);

    $response = $this->actingAs($seller)
        ->withSession([ResolveActiveStore::SESSION_KEY => $foreignStore->id])
        ->get('/orders');

    $response->assertOk();

    $trackingNumbers = collect(
        $response->viewData('page')['props']['orders']['data'] ?? []
    )->pluck('tracking_number');

    expect($trackingNumbers)->toContain($ownOrder->tracking_number)
        ->not->toContain($foreignOrder->tracking_number);
});

test('switching store is refused when the user is not a member', function () {
    $seller = storeTestUser(Role::SELLER);
    storeTestStore($seller, 'Store A');

    $intruder = storeTestUser(Role::SELLER);
    $foreignStore = storeTestStore($intruder, 'Foreign Store');

    $this->actingAs($seller)
        ->put('/stores/active', ['store_id' => $foreignStore->id])
        ->assertSessionHasErrors('store_id');
});

test('a store switch narrows the listing to the newly selected store', function () {
    $seller = storeTestUser(Role::SELLER);
    $storeA = storeTestStore($seller, 'Store A');
    $storeB = storeTestStore($seller, 'Store B');
    $city = storeTestCity();
    $sector = storeTestSector($city);

    storeTestOrder($seller, $storeA, $city, $sector);
    $orderB = storeTestOrder($seller, $storeB, $city, $sector);

    $this->actingAs($seller)
        ->withSession([ResolveActiveStore::SESSION_KEY => $storeA->id])
        ->put('/stores/active', ['store_id' => $storeB->id])
        ->assertSessionHasNoErrors();

    $response = $this->actingAs($seller)->get('/orders');

    $trackingNumbers = collect(
        $response->viewData('page')['props']['orders']['data'] ?? []
    )->pluck('tracking_number');

    expect($trackingNumbers)->toEqual(collect([$orderB->tracking_number]));
});

test('back office staff are never confined to a store', function () {
    $seller = storeTestUser(Role::SELLER);
    $storeA = storeTestStore($seller, 'Store A');
    $storeB = storeTestStore($seller, 'Store B');
    $city = storeTestCity();
    $sector = storeTestSector($city);

    storeTestOrder($seller, $storeA, $city, $sector);
    storeTestOrder($seller, $storeB, $city, $sector);

    $admin = storeTestUser(Role::ADMIN);

    $response = $this->actingAs($admin)->get('/orders');

    $response->assertOk();

    expect(collect($response->viewData('page')['props']['orders']['data'] ?? []))
        ->toHaveCount(2);
});

test('a seller without any store keeps seeing his own data', function () {
    $seller = storeTestUser(Role::SELLER);
    $city = storeTestCity();
    $sector = storeTestSector($city);

    Order::acrossStores()->create([
        'tracking_number' => 'STR-2026-000001',
        'seller_id' => $seller->id,
        'customer_first_name' => 'Legacy',
        'customer_last_name' => 'Order',
        'customer_phone' => '0644444444',
        'customer_address' => 'Legacy address',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 100,
        'delivery_price' => 25,
        'status' => OrderStatus::CREATED->value,
    ]);

    $response = $this->actingAs($seller)->get('/orders');

    $response->assertOk();

    expect(collect($response->viewData('page')['props']['orders']['data'] ?? []))
        ->toHaveCount(1);
});
