<?php

use App\Enums\OrderFailureReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
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

    $this->city = City::query()->create([
        'name' => 'Flag City',
        'code' => 'FLC',
        'region' => 'Test',
        'is_active' => true,
    ]);
});

function flagUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function flagOrder(User $seller, City $city, array $attributes = []): Order
{
    return Order::query()->create(array_merge([
        'tracking_number' => 'FLG-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000000',
        'customer_address' => '3 Flag Street',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150,
        'delivery_price' => 25,
        'status' => OrderStatus::OUT_FOR_DELIVERY->value,
    ], $attributes))->fresh();
}

it('shows the last motif next to a parcel that is still out', function () {
    $seller = flagUser(Role::SELLER);

    flagOrder($seller, $this->city, [
        'failure_reason' => OrderFailureReason::CUSTOMER_ABSENT->value,
        'failed_attempts_count' => 1,
        'failed_at' => now(),
    ]);

    $this->actingAs(flagUser(Role::ADMIN))
        ->get(route('orders.index'))
        ->assertInertia(function ($page) {
            $order = $page->toArray()['props']['orders']['data'][0];

            expect($order['status'])->toBe(OrderStatus::OUT_FOR_DELIVERY->value)
                ->and($order['failure_reason'])->toBe(OrderFailureReason::CUSTOMER_ABSENT->value)
                ->and($order['failure_reason_label'])->toBe(OrderFailureReason::CUSTOMER_ABSENT->label())
                ->and($order['failure_reason_color'])->not->toBeNull()
                ->and($order['failed_attempts_count'])->toBe(1);
        });
});

it('leaves the flag empty while nothing has gone wrong', function () {
    $seller = flagUser(Role::SELLER);
    flagOrder($seller, $this->city);

    $this->actingAs(flagUser(Role::ADMIN))
        ->get(route('orders.index'))
        ->assertInertia(function ($page) {
            $order = $page->toArray()['props']['orders']['data'][0];

            expect($order['failure_reason'])->toBeNull()
                ->and($order['failure_reason_label'])->toBeNull();
        });
});

it('carries the motif onto the parcel own screen', function () {
    $seller = flagUser(Role::SELLER);

    $order = flagOrder($seller, $this->city, [
        'failure_reason' => OrderFailureReason::CUSTOMER_UNREACHABLE->value,
        'failed_attempts_count' => 2,
        'failed_at' => now(),
    ]);

    $this->actingAs(flagUser(Role::ADMIN))
        ->get(route('orders.show', $order))
        ->assertInertia(function ($page) {
            $payload = $page->toArray()['props']['order'];

            expect($payload['failure_reason_label'])->toBe(OrderFailureReason::CUSTOMER_UNREACHABLE->label())
                ->and($payload['failure_reason_icon'])->not->toBeNull()
                ->and($payload['status'])->toBe(OrderStatus::OUT_FOR_DELIVERY->value);
        });
});

it('shows the driver why his round still holds the parcel', function () {
    $seller = flagUser(Role::SELLER);
    $driver = flagUser(Role::DRIVER);

    flagOrder($seller, $this->city, [
        'driver_id' => $driver->id,
        'failure_reason' => OrderFailureReason::WRONG_ADDRESS->value,
        'failed_attempts_count' => 1,
        'failed_at' => now(),
    ]);

    $this->actingAs($driver)
        ->get(route('orders.index'))
        ->assertInertia(function ($page) {
            $order = $page->toArray()['props']['orders']['data'][0];

            expect($order['failure_reason_label'])->toBe(OrderFailureReason::WRONG_ADDRESS->label());
        });
});
