<?php

use App\Enums\OrderCreationSource;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

test('orders create and new route is registered', function () {
    expect(route('orders.store-and-new'))->toContain('/orders/create-and-new');
});

test('orders create route accepts clone query parameter', function () {
    expect(route('orders.create', ['clone' => 12]))->toContain('clone=12');
});

function webFormSeller(): User
{
    $role = Role::query()->where('name', Role::SELLER)->firstOrFail();
    $seller = User::factory()->create(['role_id' => $role->id]);
    $seller->roles()->sync([$role->id]);

    return $seller->fresh(['roles.permissions']);
}

/**
 * Every key the create page posts, with the empty strings it really sends.
 *
 * The other creation tests drive the JSON API with a minimal body, so nothing
 * covered the browser payload — which is how an optional field left blank could
 * reach a non-nullable column as null and break the form for every seller.
 *
 * @return array<string, mixed>
 */
function webFormPayload(City $city, Sector $sector, array $overrides = []): array
{
    return array_merge([
        'customer_first_name' => 'Ayoub',
        'customer_last_name' => 'Outiti',
        'customer_phone' => '0649440905',
        'customer_address' => 'Lots 6 appt 36, ERAC Sidi Brahim',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => 'CASH',
        'order_amount' => '388',
        'order_value' => '',
        'delivery_price' => '40',
        'delivery_included' => false,
        'notes' => '',
        'is_fragile' => false,
        'can_be_opened' => false,
        'option_exchange' => false,
        'items' => [],
        'discount_amount' => '',
    ], $overrides);
}

function webFormDestination(): array
{
    $city = City::query()->create([
        'name' => 'Fes', 'code' => 'FES', 'region' => 'Fes-Meknes', 'is_active' => true,
    ]);

    $sector = Sector::query()->create([
        'city_id' => $city->id, 'name' => 'Sidi Brahim',
        'delivery_price' => 40, 'return_price' => 15, 'is_active' => true,
    ]);

    return [$city, $sector];
}

test('the create form saves an order with every optional field left blank', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    [$city, $sector] = webFormDestination();

    $this->actingAs(webFormSeller())
        ->post(route('orders.store'), webFormPayload($city, $sector))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $order = Order::query()->firstOrFail();

    expect((float) $order->discount_amount)->toBe(0.0)
        ->and($order->notes)->toBeNull()
        ->and((float) $order->total_amount)->toBe(428.0)
        ->and($order->creation_source)->toBe(OrderCreationSource::Manual);
});

test('the create form ignores a client-supplied creation_source', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    [$city, $sector] = webFormDestination();

    $this->actingAs(webFormSeller())
        ->post(route('orders.store'), webFormPayload($city, $sector, [
            'creation_source' => 'integration',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Order::query()->firstOrFail()->creation_source)->toBe(OrderCreationSource::Manual);
});

test('the create form honours the delivery included switch', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    [$city, $sector] = webFormDestination();

    $this->actingAs(webFormSeller())
        ->post(route('orders.store'), webFormPayload($city, $sector, ['delivery_included' => true]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $order = Order::query()->firstOrFail();

    expect($order->delivery_included)->toBeTrue()
        ->and((float) $order->total_amount)->toBe(388.0)
        ->and((float) $order->delivery_price)->toBe(40.0);
});

test('the create form rejects phones that do not match 0XXXXXXXXX', function (string $phone) {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    [$city, $sector] = webFormDestination();

    $this->actingAs(webFormSeller())
        ->from(route('orders.create'))
        ->post(route('orders.store'), webFormPayload($city, $sector, ['customer_phone' => $phone]))
        ->assertSessionHasErrors('customer_phone')
        ->assertRedirect(route('orders.create'));

    expect(Order::query()->exists())->toBeFalse();
})->with([
    'missing trunk zero' => '6123456789',
    'too short' => '061234567',
    'too long' => '06123456789',
    'letters' => '06ABCDEFGH',
    'international' => '+212612345678',
    'spaces' => '06 12 34 56 78',
]);

test('a seller cannot undercut the sector delivery price when creating an order', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    [$city, $sector] = webFormDestination();

    $this->actingAs(webFormSeller())
        ->post(route('orders.store'), webFormPayload($city, $sector, ['delivery_price' => '1']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $order = Order::query()->firstOrFail();

    expect((float) $order->delivery_price)->toBe(40.0)
        ->and((float) $order->total_amount)->toBe(428.0);
});
