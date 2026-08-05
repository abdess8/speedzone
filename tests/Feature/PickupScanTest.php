<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PickupRequestStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PickupRequest;
use App\Models\PickupStatusHistory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function pickupScanUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function pickupScanCity(): City
{
    return City::query()->create([
        'name' => 'Test City',
        'code' => 'TST',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function pickupScanOrder(User $seller, City $city, OrderStatus $status, ?PickupRequest $pickup = null): Order
{
    $order = Order::query()->create([
        'tracking_number' => 'TST-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'pickup_request_id' => $pickup?->id,
        'customer_first_name' => 'John',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000000',
        'customer_address' => '123 Test Street',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 100,
        'delivery_price' => 20,
        'status' => $status->value,
    ]);

    $order->recordStatus($status, $seller, 'Test seed status.');

    return $order->fresh(['pickupRequest', 'city']);
}

function pickupScanRequest(User $seller, User $driver, array $orders): PickupRequest
{
    $pickup = PickupRequest::query()->create([
        'reference' => 'PKP-2026-'.random_int(100000, 999999),
        'created_by' => $seller->id,
        'assigned_to' => $driver->id,
        'status' => PickupRequestStatus::WAITING_FOR_PICKUP->value,
        'pickup_address' => 'Warehouse A',
        'number_of_packages' => count($orders),
        'total_orders_amount' => collect($orders)->sum('order_amount'),
    ]);

    $pickup->recordStatus(PickupRequestStatus::WAITING_FOR_PICKUP, $seller, null, 'Pickup created for tests.');

    foreach ($orders as $order) {
        $order->update(['pickup_request_id' => $pickup->id]);
    }

    return $pickup->fresh(['orders']);
}

test('driver can validate an assigned waiting pickup order via scan endpoint', function () {
    $seller = pickupScanUser(Role::SELLER);
    $driver = pickupScanUser(Role::DRIVER);
    $city = pickupScanCity();

    $order = pickupScanOrder($seller, $city, OrderStatus::WAITING_PICKUP);
    pickupScanRequest($seller, $driver, [$order]);

    Sanctum::actingAs($driver);

    $response = $this->postJson(route('api.pickup.scan'), [
        'tracking_number' => $order->tracking_number,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'valid' => true,
            'order' => [
                'tracking_number' => $order->tracking_number,
                'status' => OrderStatus::WAITING_PICKUP->value,
            ],
        ]);
});

test('driver scan rejects order not assigned to them', function () {
    $seller = pickupScanUser(Role::SELLER);
    $driver = pickupScanUser(Role::DRIVER);
    $otherDriver = pickupScanUser(Role::DRIVER);
    $city = pickupScanCity();

    $order = pickupScanOrder($seller, $city, OrderStatus::WAITING_PICKUP);
    pickupScanRequest($seller, $otherDriver, [$order]);

    Sanctum::actingAs($driver);

    $response = $this->postJson(route('api.pickup.scan'), [
        'tracking_number' => $order->tracking_number,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => false,
            'message' => 'This order is not assigned to you.',
        ]);
});

test('driver bulk status update marks order and pickup as picked up with history', function () {
    $seller = pickupScanUser(Role::SELLER);
    $driver = pickupScanUser(Role::DRIVER);
    $city = pickupScanCity();

    $order = pickupScanOrder($seller, $city, OrderStatus::WAITING_PICKUP);
    $pickup = pickupScanRequest($seller, $driver, [$order]);

    Sanctum::actingAs($driver);

    $response = $this->postJson(route('api.pickup.bulk-status-update'), [
        'orders' => [$order->tracking_number],
        'status' => PickupRequestStatus::PICKED_UP->value,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'updated' => 1,
        ]);

    $order->refresh();
    $pickup->refresh();

    expect($order->status)->toBe(OrderStatus::PICKED_UP)
        ->and($pickup->status)->toBe(PickupRequestStatus::PICKED_UP);

    expect(OrderStatusHistory::query()->where('order_id', $order->id)->where('status', OrderStatus::PICKED_UP->value)->exists())->toBeTrue();
    expect(PickupStatusHistory::query()->where('pickup_request_id', $pickup->id)->where('new_status', PickupRequestStatus::PICKED_UP->value)->exists())->toBeTrue();
});

test('admin can scan picked up orders and bulk update them to in depot', function () {
    $seller = pickupScanUser(Role::SELLER);
    $admin = pickupScanUser(Role::ADMIN);
    $driver = pickupScanUser(Role::DRIVER);
    $city = pickupScanCity();

    $order = pickupScanOrder($seller, $city, OrderStatus::PICKED_UP);
    $pickup = PickupRequest::query()->create([
        'reference' => 'PKP-2026-'.random_int(100000, 999999),
        'created_by' => $seller->id,
        'assigned_to' => $driver->id,
        'status' => PickupRequestStatus::PICKED_UP->value,
        'pickup_address' => 'Warehouse A',
        'number_of_packages' => 1,
        'total_orders_amount' => 100,
    ]);
    $order->update(['pickup_request_id' => $pickup->id]);

    Sanctum::actingAs($admin);

    $scanResponse = $this->postJson(route('api.pickup.scan'), [
        'tracking_number' => $order->tracking_number,
    ]);

    $scanResponse->assertOk()->assertJson(['success' => true, 'valid' => true]);

    $updateResponse = $this->postJson(route('api.pickup.bulk-status-update'), [
        'orders' => [$order->tracking_number],
        'status' => PickupRequestStatus::IN_DEPOT->value,
    ]);

    $updateResponse->assertOk()->assertJson(['success' => true, 'updated' => 1]);

    $order->refresh();
    $pickup->refresh();

    expect($order->status)->toBe(OrderStatus::IN_DEPOT)
        ->and($pickup->status)->toBe(PickupRequestStatus::IN_DEPOT);
});

test('admin scan rejects waiting pickup orders', function () {
    $seller = pickupScanUser(Role::SELLER);
    $admin = pickupScanUser(Role::ADMIN);
    $city = pickupScanCity();

    $order = pickupScanOrder($seller, $city, OrderStatus::WAITING_PICKUP);

    Sanctum::actingAs($admin);

    $response = $this->postJson(route('api.pickup.scan'), [
        'tracking_number' => $order->tracking_number,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => false,
            'message' => 'You cannot scan this order.',
        ]);
});

test('driver cannot bulk update with invalid target status', function () {
    $seller = pickupScanUser(Role::SELLER);
    $driver = pickupScanUser(Role::DRIVER);
    $city = pickupScanCity();

    $order = pickupScanOrder($seller, $city, OrderStatus::WAITING_PICKUP);
    pickupScanRequest($seller, $driver, [$order]);

    Sanctum::actingAs($driver);

    $response = $this->postJson(route('api.pickup.bulk-status-update'), [
        'orders' => [$order->tracking_number],
        'status' => PickupRequestStatus::IN_DEPOT->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('driver cannot bulk update another drivers order', function () {
    $seller = pickupScanUser(Role::SELLER);
    $driver = pickupScanUser(Role::DRIVER);
    $otherDriver = pickupScanUser(Role::DRIVER);
    $city = pickupScanCity();

    $order = pickupScanOrder($seller, $city, OrderStatus::WAITING_PICKUP);
    pickupScanRequest($seller, $otherDriver, [$order]);

    Sanctum::actingAs($driver);

    $response = $this->postJson(route('api.pickup.bulk-status-update'), [
        'orders' => [$order->tracking_number],
        'status' => PickupRequestStatus::PICKED_UP->value,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['orders']);
});

test('pickup scan routes are registered', function () {
    expect(route('pickup.scan'))->toContain('/pickup/scan');
    expect(route('pickup.bulk-status-update'))->toContain('/pickup/bulk-status-update');
});
