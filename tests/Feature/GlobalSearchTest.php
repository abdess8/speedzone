<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Role;
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

function searchUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function searchCity(): City
{
    return City::query()->firstOrCreate(
        ['code' => 'SRC'],
        ['name' => 'Searchville', 'region' => 'Test', 'is_active' => true]
    );
}

function searchOrder(User $seller, array $attributes = []): Order
{
    return Order::query()->create(array_merge([
        'tracking_number' => 'SRCH-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'customer_first_name' => 'Yassine',
        'customer_last_name' => 'Bennani',
        'customer_phone' => '0611223344',
        'customer_address' => '4 Search Street',
        'city_id' => searchCity()->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 200,
        'delivery_price' => 25,
        'status' => OrderStatus::CREATED->value,
    ], $attributes))->fresh();
}

/**
 * @return array<int, array<string, mixed>>
 */
function hitsFor(array $groups, string $key): array
{
    foreach ($groups as $group) {
        if ($group['key'] === $key) {
            return $group['hits'];
        }
    }

    return [];
}

it('finds an order by tracking number, customer name and phone', function () {
    $order = searchOrder(searchUser(Role::SELLER));
    $admin = searchUser(Role::ADMIN);

    foreach ([$order->tracking_number, 'Bennani', '0611223344'] as $term) {
        $response = $this->actingAs($admin)
            ->getJson(route('search.global', ['q' => $term]))
            ->assertOk();

        $hits = hitsFor($response->json('groups'), 'orders');

        expect(array_column($hits, 'id'))->toContain($order->id);
    }
});

it('ships the preview alongside the row so hovering costs no request', function () {
    $order = searchOrder(searchUser(Role::SELLER));

    $response = $this->actingAs(searchUser(Role::ADMIN))
        ->getJson(route('search.global', ['q' => $order->tracking_number]))
        ->assertOk();

    $hit = hitsFor($response->json('groups'), 'orders')[0];

    expect($hit['url'])->toBe(route('orders.show', $order))
        ->and($hit['badge'])->not->toBeNull()
        ->and(array_column($hit['preview'], 'value'))->toContain('0611223344');
});

it('never surfaces an order belonging to another seller', function () {
    $stranger = searchOrder(searchUser(Role::SELLER));
    $seller = searchUser(Role::SELLER);
    $own = searchOrder($seller);

    $response = $this->actingAs($seller)
        ->getJson(route('search.global', ['q' => 'SRCH-2026-']))
        ->assertOk();

    $ids = array_column(hitsFor($response->json('groups'), 'orders'), 'id');

    expect($ids)->toContain($own->id)
        ->and($ids)->not->toContain($stranger->id);
});

it('limits a driver to the orders he is carrying', function () {
    $driver = searchUser(Role::DRIVER);
    $seller = searchUser(Role::SELLER);
    $mine = searchOrder($seller, ['driver_id' => $driver->id, 'status' => OrderStatus::OUT_FOR_DELIVERY->value]);
    $other = searchOrder($seller);

    $response = $this->actingAs($driver)
        ->getJson(route('search.global', ['q' => 'SRCH-2026-']))
        ->assertOk();

    $ids = array_column(hitsFor($response->json('groups'), 'orders'), 'id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

it('merges partner orders into the same group as the native ones', function () {
    $partner = Partner::query()->create([
        'name' => 'Searchable Partner',
        'is_active' => true,
    ]);

    $seller = searchUser(Role::SELLER);
    $native = searchOrder($seller);
    $ingested = searchOrder($seller, ['partner_id' => $partner->id]);

    $response = $this->actingAs(searchUser(Role::ADMIN))
        ->getJson(route('search.global', ['q' => 'SRCH-2026-']))
        ->assertOk();

    $hits = hitsFor($response->json('groups'), 'orders');

    expect(array_column($hits, 'id'))->toContain($native->id, $ingested->id);
});

it('sends a driver to his list rather than to a detail page he cannot open', function () {
    $driver = searchUser(Role::DRIVER);
    $order = searchOrder(searchUser(Role::SELLER), [
        'driver_id' => $driver->id,
        'status' => OrderStatus::OUT_FOR_DELIVERY->value,
    ]);

    $hits = $this->actingAs($driver)
        ->getJson(route('search.global', ['q' => $order->tracking_number]))
        ->assertOk()
        ->json('groups');

    expect(hitsFor($hits, 'orders')[0]['url'])
        ->toBe(route('orders.index', ['tracking_number' => $order->tracking_number]));
});

it('offers only the objects the account may read', function () {
    $adminScopes = array_column(
        $this->actingAs(searchUser(Role::ADMIN))
            ->getJson(route('search.global'))
            ->json('scopes'),
        'key'
    );

    $sellerScopes = array_column(
        $this->actingAs(searchUser(Role::SELLER))
            ->getJson(route('search.global'))
            ->json('scopes'),
        'key'
    );

    expect($adminScopes)->toContain('orders', 'users', 'cities', 'sectors')
        ->and($sellerScopes)->toContain('orders')
        ->and($sellerScopes)->not->toContain('users');
});

it('searches a single object when the scope is narrowed', function () {
    $order = searchOrder(searchUser(Role::SELLER), ['customer_last_name' => 'Cherkaoui']);
    User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Cherkaoui']);

    $groups = $this->actingAs(searchUser(Role::ADMIN))
        ->getJson(route('search.global', ['q' => 'Cherkaoui', 'scope' => 'orders']))
        ->assertOk()
        ->json('groups');

    expect(array_column($groups, 'key'))->toBe(['orders'])
        ->and(array_column($groups[0]['hits'], 'id'))->toContain($order->id);
});

it('finds a user by name, id number and email', function () {
    $target = User::factory()->create([
        'first_name' => 'Salma',
        'last_name' => 'Idrissi',
        'cin' => 'BK998877',
        'email' => 'salma.idrissi@example.test',
    ]);

    foreach (['Idrissi', 'BK998877', 'salma.idrissi@example'] as $term) {
        $hits = $this->actingAs(searchUser(Role::ADMIN))
            ->getJson(route('search.global', ['q' => $term]))
            ->assertOk()
            ->json('groups');

        expect(array_column(hitsFor($hits, 'users'), 'id'))->toContain($target->id);
    }
});

it('ignores a term too short to narrow anything down', function () {
    searchOrder(searchUser(Role::SELLER));

    $response = $this->actingAs(searchUser(Role::ADMIN))
        ->getJson(route('search.global', ['q' => 'S']))
        ->assertOk();

    expect($response->json('groups'))->toBe([])
        ->and($response->json('scopes'))->not->toBeEmpty();
});

it('is closed to guests', function () {
    $this->getJson(route('search.global', ['q' => 'anything']))->assertUnauthorized();
});
