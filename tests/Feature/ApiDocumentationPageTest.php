<?php

use App\Enums\OrderStatus;
use App\Models\City;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * The integration guide is written for merchants, so its route is gated on
 * `orders.create` rather than on the partner permissions the placeholder page
 * inherited — a seller could not open his own API documentation.
 */
beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function apiDocsUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();

    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

it('lets a seller open the API documentation', function () {
    $seller = apiDocsUser(Role::SELLER);

    $this->actingAs($seller)
        ->get(route('api-integrations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/api-integrations')
            ->has('apiBaseUrl')
            ->has('tokensUrl')
            ->where('storeHeader', 'X-Store-Id')
            ->where('rateLimit', 60)
        );
});

it('keeps the API documentation away from drivers', function () {
    $driver = apiDocsUser(Role::DRIVER);

    $this->actingAs($driver)
        ->get(route('api-integrations.index'))
        ->assertForbidden();
});

it('lists the stores a seller can target with the store header', function () {
    $seller = apiDocsUser(Role::SELLER);

    $main = Store::query()->create([
        'owner_id' => $seller->id,
        'name' => 'Atlas Concept',
        'is_default' => true,
        'is_active' => true,
    ]);
    $secondary = Store::query()->create([
        'owner_id' => $seller->id,
        'name' => 'Atlas Outlet',
        'is_default' => false,
        'is_active' => true,
    ]);
    $seller->stores()->sync([$main->id, $secondary->id]);

    $this->actingAs($seller)
        ->get(route('api-integrations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('stores', 2)
            // Ordered default-first so the samples pick a store the reader
            // actually writes to when he has not chosen one.
            ->where('stores.0.id', $main->id)
            ->where('stores.0.is_default', true)
            ->where('stores.1.name', 'Atlas Outlet')
        );
});

it('quotes the host the reader reached us on when developing locally', function () {
    $seller = apiDocsUser(Role::SELLER);

    // What `php artisan serve` does when port 8000 is taken, against the
    // default APP_URL that carries no port at all.
    app()['env'] = 'local';
    config(['app.url' => 'http://localhost']);

    $this->actingAs($seller)
        ->get('http://127.0.0.1:8002'.route('api-integrations.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('apiBaseUrl', 'http://127.0.0.1:8002'));
});

it('keeps the canonical url once deployed, whatever host was used to arrive', function () {
    $seller = apiDocsUser(Role::SELLER);

    app()['env'] = 'production';
    config(['app.url' => 'https://app.speedzone.ma']);

    $this->actingAs($seller)
        ->get('http://10.0.0.4'.route('api-integrations.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('apiBaseUrl', 'https://app.speedzone.ma'));
});

it('seeds the Postman collection with a city and one of its own sectors', function () {
    $seller = apiDocsUser(Role::SELLER);

    $city = City::query()->create([
        'name' => 'Tanger',
        'code' => 'TNG',
        'region' => 'Nord',
        'is_active' => true,
    ]);
    $sector = Sector::query()->create([
        'city_id' => $city->id,
        'name' => 'Centre Ville',
        'delivery_price' => 25.00,
        'is_active' => true,
    ]);

    $this->actingAs($seller)
        ->get(route('api-integrations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('examples.city_id', $city->id)
            ->where('examples.sector_id', $sector->id)
            ->where('examples.city_name', 'Tanger')
        );
});

it('never offers an inactive sector as the example payload', function () {
    $seller = apiDocsUser(Role::SELLER);

    $city = City::query()->create([
        'name' => 'Tanger',
        'code' => 'TNG',
        'region' => 'Nord',
        'is_active' => true,
    ]);
    Sector::query()->create([
        'city_id' => $city->id,
        'name' => 'Retired Sector',
        'delivery_price' => 25.00,
        'is_active' => false,
    ]);

    // A create-order request built from an inactive sector would 422, so the
    // collection is better off with no default than with a broken one.
    $this->actingAs($seller)
        ->get(route('api-integrations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('examples.sector_id', null));
});

it('exposes every order status so the reference table cannot drift', function () {
    $seller = apiDocsUser(Role::SELLER);

    $this->actingAs($seller)
        ->get(route('api-integrations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orderStatuses', count(OrderStatus::cases()))
            ->where('orderStatuses.0.value', 'CREATED')
            ->has('statusGroups', 4)
        );
});
