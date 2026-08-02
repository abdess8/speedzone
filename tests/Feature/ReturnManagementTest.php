<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\ReturnTransitionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function returnTestCity(): City
{
    return City::query()->create([
        'name' => 'Return City',
        'code' => 'RTN',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function returnTestUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id, 'city_id' => null]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function returnTestOrder(User $seller, City $city, OrderStatus $status): Order
{
    return Order::query()->create([
        'tracking_number' => 'RTT-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Customer',
        'customer_phone' => '0611111111',
        'customer_address' => '456 Return Street',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150,
        'delivery_price' => 25,
        'status' => $status->value,
    ]);
}

test('driver opens the return and the hub signs it in', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $driver = returnTestUser(Role::DRIVER);
    $hub = returnTestUser(Role::DISPATCHER);
    $order = returnTestOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::RETURN_REQUESTED)
        ->and($return->status)->toBe(ReturnStatus::CREATED)
        ->and($order->return_id)->toBe($return->id);

    app(ReturnTransitionService::class)->receiveAtHub($return->fresh(), $hub);

    $order->refresh();
    $return->refresh();

    expect($order->status)->toBe(OrderStatus::RETURN_IN_PROGRESS)
        ->and($return->status)->toBe(ReturnStatus::RECEIVED_AT_HUB)
        ->and($return->statusHistories()->count())->toBeGreaterThan(1);
});

test('a driver may not sign a return into the hub', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $driver = returnTestUser(Role::DRIVER);
    $order = returnTestOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    app(ReturnTransitionService::class)->receiveAtHub($return->fresh(), $driver);
})->throws(AuthorizationException::class);

test('the return walks the six steps back to the vendor', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $driver = returnTestUser(Role::DRIVER);
    $admin = returnTestUser(Role::ADMIN);
    $order = returnTestOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    $transitions = app(ReturnTransitionService::class);

    foreach (array_slice(ReturnStatus::pipeline(), 1) as $status) {
        $transitions->transition($return->fresh(), $status, $admin);
    }

    $order->refresh();

    expect($return->fresh()->status)->toBe(ReturnStatus::DELIVERED_TO_VENDOR)
        ->and($order->status)->toBe(OrderStatus::RETURNED)
        ->and($order->is_returned)->toBeTrue()
        ->and($order->returned_at)->not->toBeNull();
});

test('the return workflow refuses to skip a step', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $driver = returnTestUser(Role::DRIVER);
    $admin = returnTestUser(Role::ADMIN);
    $order = returnTestOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);

    $return = app(ReturnService::class)->create(
        $order,
        $driver,
        ReturnInitiatedByRole::DRIVER,
        ReturnReason::CUSTOMER_REFUSED->value,
    );

    app(ReturnTransitionService::class)
        ->transition($return->fresh(), ReturnStatus::ARRIVED_VENDOR_HUB, $admin);
})->throws(ValidationException::class);

test('seller can request return for delivered order', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);

    $return = app(ReturnService::class)->create(
        $order,
        $seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );

    expect($return->initiated_by_role)->toBe(ReturnInitiatedByRole::SELLER)
        ->and($order->fresh()->status)->toBe(OrderStatus::RETURN_REQUESTED);
});

test('customer data updates stay on return entity only', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);
    $order->update(['customer_first_name' => 'Original']);

    $return = app(ReturnService::class)->create(
        $order,
        $seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );

    app(ReturnService::class)->updateCustomerData($return, $seller, [
        'updated_customer_name' => 'Updated Name',
    ]);

    $return->refresh();

    expect($return->updated_customer_name)->toBe('Updated Name')
        ->and($order->fresh()->customer_first_name)->toBe('Original');
});

test('returns index is accessible with permission', function () {
    $admin = returnTestUser(Role::ADMIN);

    $this->actingAs($admin)
        ->get(route('returns.index'))
        ->assertOk();
});

test('seller can access returns index and create return request', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);

    $this->actingAs($seller)
        ->get(route('returns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('returns/index')
            ->where('can.create_request', true)
            ->where('can.manage', false)
        );

    $this->actingAs($seller)
        ->post(route('returns.store'), [
            'order_id' => $order->id,
            'reason' => ReturnReason::SELLER_REQUESTED->value,
        ])
        ->assertRedirect();
});

test('admin cannot create seller return request', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $admin = returnTestUser(Role::ADMIN);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);

    $this->actingAs($admin)
        ->get(route('returns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.create_request', false)
        );

    $this->actingAs($admin)
        ->post(route('returns.store'), [
            'order_id' => $order->id,
            'reason' => ReturnReason::SELLER_REQUESTED->value,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('returns.eligible-orders'))
        ->assertForbidden();
});

test('one order cannot have two non-cancelled returns', function () {
    $city = returnTestCity();
    $seller = returnTestUser(Role::SELLER);
    $order = returnTestOrder($seller, $city, OrderStatus::DELIVERED);

    app(ReturnService::class)->create(
        $order,
        $seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );

    app(ReturnService::class)->create(
        $order->fresh(),
        $seller,
        ReturnInitiatedByRole::SELLER,
        ReturnReason::SELLER_REQUESTED->value,
    );
})->throws(ValidationException::class);
