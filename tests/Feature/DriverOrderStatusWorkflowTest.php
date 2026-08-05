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
});

function workflowUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function workflowCity(): City
{
    return City::query()->create([
        'name' => 'Workflow City',
        'code' => 'WFC',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function workflowOrder(User $seller, City $city, OrderStatus $status, ?User $driver = null): Order
{
    return Order::query()->create([
        'tracking_number' => 'WFL-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'driver_id' => $driver?->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000000',
        'customer_address' => '1 Workflow Street',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150,
        'delivery_price' => 25,
        'status' => $status->value,
    ])->fresh();
}

it('lets an assigned driver move an order out for delivery', function () {
    $seller = workflowUser(Role::SELLER);
    $driver = workflowUser(Role::DRIVER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::IN_DELIVERY_CITY, $driver);

    $this->actingAs($driver)
        ->post(route('orders.bulk-status'), [
            'ids' => [$order->id],
            'to_status' => OrderStatus::OUT_FOR_DELIVERY->value,
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);
});

it('records the failure reason when an order is taken off the round', function () {
    $seller = workflowUser(Role::SELLER);
    $driver = workflowUser(Role::DRIVER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    $this->actingAs($driver)
        ->post(route('orders.bulk-status'), [
            'ids' => [$order->id],
            'to_status' => OrderStatus::READY_TO_RETURN->value,
            'failure_reason' => OrderFailureReason::CUSTOMER_REFUSED->value,
            'failure_note' => 'Refused at the door.',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::READY_TO_RETURN)
        ->and($order->failure_reason)->toBe(OrderFailureReason::CUSTOMER_REFUSED)
        ->and($order->failure_note)->toBe('Refused at the door.')
        ->and($order->failed_at)->not->toBeNull();

    // The reason must reach the tracking timeline, which only renders comments.
    expect($order->statusHistories()->latest('id')->first()->comment)
        ->toContain(OrderFailureReason::CUSTOMER_REFUSED->label());
});

it('rejects a return-ready transition submitted without a reason', function () {
    $seller = workflowUser(Role::SELLER);
    $driver = workflowUser(Role::DRIVER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    $this->actingAs($driver)
        ->post(route('orders.bulk-status'), [
            'ids' => [$order->id],
            'to_status' => OrderStatus::READY_TO_RETURN->value,
        ])
        ->assertSessionHasErrors('failure_reason');

    expect($order->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);
});

it('refuses to move an order into the retired failed status', function () {
    $seller = workflowUser(Role::SELLER);
    $driver = workflowUser(Role::DRIVER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    $this->actingAs($driver)
        ->postJson(route('api.orders.transition', $order), [
            'to_status' => OrderStatus::FAILED->value,
            'failure_reason' => OrderFailureReason::CUSTOMER_ABSENT->value,
        ])
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);
});

it('does not let a driver touch an order assigned to somebody else', function () {
    $seller = workflowUser(Role::SELLER);
    $driver = workflowUser(Role::DRIVER);
    $otherDriver = workflowUser(Role::DRIVER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $otherDriver);

    $this->actingAs($driver)
        ->post(route('orders.bulk-status'), [
            'ids' => [$order->id],
            'to_status' => OrderStatus::DELIVERED->value,
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);
});

it('forbids a driver from transitioning to a status outside his workflow', function () {
    $seller = workflowUser(Role::SELLER);
    $driver = workflowUser(Role::DRIVER);
    $city = workflowCity();

    // CANCELED is reachable from OUT_FOR_DELIVERY in the graph, but the driver
    // holds no `orders.transition.to_canceled` permission.
    $order = workflowOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    $this->actingAs($driver)
        ->postJson(route('api.orders.transition', $order), [
            'to_status' => OrderStatus::CANCELED->value,
        ])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);
});

it('lets a dispatcher update a status without being a super admin', function () {
    $seller = workflowUser(Role::SELLER);
    $dispatcher = workflowUser(Role::DISPATCHER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::IN_DELIVERY_CITY);

    expect($dispatcher->isSuperAdmin())->toBeFalse();

    $this->actingAs($dispatcher)
        ->post(route('orders.bulk-status'), [
            'ids' => [$order->id],
            'to_status' => OrderStatus::OUT_FOR_DELIVERY->value,
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);
});

it('never lets a driver edit the content of an assigned order', function () {
    $seller = workflowUser(Role::SELLER);
    $driver = workflowUser(Role::DRIVER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    expect($driver->can('updateStatus', $order))->toBeTrue()
        ->and($driver->can('update', $order))->toBeFalse();
});

it('forbids a driver from opening the detail screen of an order assigned to him', function () {
    $seller = workflowUser(Role::SELLER);
    $driver = workflowUser(Role::DRIVER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    // He may read the row in his list, but the detail screen also carries the
    // seller, the billing trail and the change history.
    expect($driver->can('view', $order))->toBeTrue()
        ->and($driver->can('viewDetails', $order))->toBeFalse();

    $this->actingAs($driver)
        ->get(route('orders.show', $order))
        ->assertForbidden();
});

it('still lets a driver list the orders assigned to him', function () {
    $seller = workflowUser(Role::SELLER);
    $driver = workflowUser(Role::DRIVER);
    $city = workflowCity();

    workflowOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY, $driver);

    $this->actingAs($driver)
        ->get(route('orders.index'))
        ->assertOk();
});

it('keeps the detail screen open to the seller who owns the order', function () {
    $seller = workflowUser(Role::SELLER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::CREATED);

    expect($seller->can('viewDetails', $order))->toBeTrue();

    $this->actingAs($seller)
        ->get(route('orders.show', $order))
        ->assertOk();
});

it('keeps the detail screen open to a dispatcher', function () {
    $seller = workflowUser(Role::SELLER);
    $dispatcher = workflowUser(Role::DISPATCHER);
    $city = workflowCity();

    $order = workflowOrder($seller, $city, OrderStatus::IN_DELIVERY_CITY);

    expect($dispatcher->can('viewDetails', $order))->toBeTrue();
});

it('restricts the list to the statuses of the requested sidebar view', function () {
    $seller = workflowUser(Role::SELLER);
    $dispatcher = workflowUser(Role::DISPATCHER);
    $city = workflowCity();

    $outForDelivery = workflowOrder($seller, $city, OrderStatus::OUT_FOR_DELIVERY);
    $delivered = workflowOrder($seller, $city, OrderStatus::DELIVERED);

    $trackingNumbers = collect(
        $this->actingAs($dispatcher)
            ->get(route('orders.index', ['status_group' => 'delivery']))
            ->viewData('page')['props']['orders']['data']
    )->pluck('tracking_number');

    expect($trackingNumbers)->toContain($outForDelivery->tracking_number)
        ->and($trackingNumbers)->not->toContain($delivered->tracking_number);
});
