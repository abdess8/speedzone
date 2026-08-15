<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
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

    $this->city = City::query()->create([
        'name' => 'Dispatch City',
        'code' => 'DPC',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $this->sector = Sector::query()->create([
        'city_id' => $this->city->id,
        'name' => 'Round A',
        'delivery_price' => 25,
        'return_price' => 15,
        'delivery_driver_price' => 10,
        'is_active' => true,
    ]);

    $this->otherSector = Sector::query()->create([
        'city_id' => $this->city->id,
        'name' => 'Round B',
        'delivery_price' => 25,
        'return_price' => 15,
        'delivery_driver_price' => 10,
        'is_active' => true,
    ]);
});

function dispatchUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function dispatchOrder(User $seller, City $city, Sector $sector, ?OrderStatus $status = null, ?User $driver = null): Order
{
    return Order::query()->create([
        'tracking_number' => 'DSP-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'driver_id' => $driver?->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000000',
        'customer_address' => '1 Dispatch Street',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150,
        'delivery_price' => 25,
        'status' => ($status ?? OrderStatus::OUT_FOR_DELIVERY)->value,
    ])->fresh();
}

it('hands every parcel of a sector to one driver in a single request', function () {
    $admin = dispatchUser(Role::ADMIN);
    $seller = dispatchUser(Role::SELLER);
    $driver = dispatchUser(Role::DRIVER);

    $first = dispatchOrder($seller, $this->city, $this->sector);
    $second = dispatchOrder($seller, $this->city, $this->sector);
    $elsewhere = dispatchOrder($seller, $this->city, $this->otherSector);

    $this->actingAs($admin)
        ->post(route('orders.dispatch-sector'), [
            'sector_id' => $this->sector->id,
            'driver_id' => $driver->id,
        ])
        ->assertRedirect();

    expect($first->fresh()->driver_id)->toBe($driver->id)
        ->and($second->fresh()->driver_id)->toBe($driver->id)
        ->and($elsewhere->fresh()->driver_id)->toBeNull();
});

it('leaves a parcel already carried by somebody else alone', function () {
    $admin = dispatchUser(Role::ADMIN);
    $seller = dispatchUser(Role::SELLER);
    $driver = dispatchUser(Role::DRIVER);
    $incumbent = dispatchUser(Role::DRIVER);

    $taken = dispatchOrder($seller, $this->city, $this->sector, driver: $incumbent);

    $this->actingAs($admin)
        ->post(route('orders.dispatch-sector'), [
            'sector_id' => $this->sector->id,
            'driver_id' => $driver->id,
        ])
        ->assertRedirect();

    expect($taken->fresh()->driver_id)->toBe($incumbent->id);
});

it('reassigns the whole round when the dispatcher asks for it', function () {
    $admin = dispatchUser(Role::ADMIN);
    $seller = dispatchUser(Role::SELLER);
    $driver = dispatchUser(Role::DRIVER);
    $incumbent = dispatchUser(Role::DRIVER);

    $taken = dispatchOrder($seller, $this->city, $this->sector, driver: $incumbent);

    $this->actingAs($admin)
        ->post(route('orders.dispatch-sector'), [
            'sector_id' => $this->sector->id,
            'driver_id' => $driver->id,
            'reassign' => true,
        ])
        ->assertRedirect();

    expect($taken->fresh()->driver_id)->toBe($driver->id);
});

it('never dispatches a parcel that has not left the hub', function () {
    $admin = dispatchUser(Role::ADMIN);
    $seller = dispatchUser(Role::SELLER);
    $driver = dispatchUser(Role::DRIVER);

    $waiting = dispatchOrder($seller, $this->city, $this->sector, OrderStatus::IN_DELIVERY_CITY);

    $this->actingAs($admin)
        ->post(route('orders.dispatch-sector'), [
            'sector_id' => $this->sector->id,
            'driver_id' => $driver->id,
        ])
        ->assertRedirect();

    expect($waiting->fresh()->driver_id)->toBeNull()
        ->and($waiting->fresh()->status)->toBe(OrderStatus::IN_DELIVERY_CITY);
});

it('refuses a user who is not a driver', function () {
    $admin = dispatchUser(Role::ADMIN);
    $seller = dispatchUser(Role::SELLER);

    $order = dispatchOrder($seller, $this->city, $this->sector);

    $this->actingAs($admin)
        ->post(route('orders.dispatch-sector'), [
            'sector_id' => $this->sector->id,
            'driver_id' => $seller->id,
        ])
        ->assertSessionHasErrors('driver_id');

    expect($order->fresh()->driver_id)->toBeNull();
});

it('keeps the dispatch screen out of a seller reach', function () {
    $seller = dispatchUser(Role::SELLER);
    $driver = dispatchUser(Role::DRIVER);

    $order = dispatchOrder($seller, $this->city, $this->sector);

    $this->actingAs($seller)
        ->post(route('orders.dispatch-sector'), [
            'sector_id' => $this->sector->id,
            'driver_id' => $driver->id,
        ])
        ->assertForbidden();

    expect($order->fresh()->driver_id)->toBeNull();
});

it('assigns the rows the dispatcher ticked', function () {
    $admin = dispatchUser(Role::ADMIN);
    $seller = dispatchUser(Role::SELLER);
    $driver = dispatchUser(Role::DRIVER);

    $picked = dispatchOrder($seller, $this->city, $this->sector);
    $ignored = dispatchOrder($seller, $this->city, $this->sector);

    $this->actingAs($admin)
        ->post(route('orders.bulk-assign-driver'), [
            'ids' => [$picked->id],
            'driver_id' => $driver->id,
        ])
        ->assertRedirect();

    expect($picked->fresh()->driver_id)->toBe($driver->id)
        ->and($ignored->fresh()->driver_id)->toBeNull();
});

it('offers the round to the drivers who cover the sector', function () {
    $admin = dispatchUser(Role::ADMIN);
    $seller = dispatchUser(Role::SELLER);
    $driver = dispatchUser(Role::DRIVER);

    $driver->sectors()->sync([$this->sector->id]);

    dispatchOrder($seller, $this->city, $this->sector);
    dispatchOrder($seller, $this->city, $this->sector, driver: $driver);

    $this->actingAs($admin)
        ->get(route('orders.index'))
        ->assertInertia(function ($page) use ($driver) {
            $rounds = $page->toArray()['props']['dispatch']['sectors'];

            expect($rounds)->toHaveCount(1)
                ->and($rounds[0]['name'])->toBe('Round A')
                ->and($rounds[0]['total'])->toBe(2)
                ->and($rounds[0]['unassigned'])->toBe(1)
                ->and($rounds[0]['drivers'][0]['id'])->toBe($driver->id);
        });
});
