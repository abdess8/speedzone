<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderChangeHistory;
use App\Models\Role;
use App\Models\Sector;
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

function orderTestUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function orderTestCity(): City
{
    return City::query()->create([
        'name' => 'Test City',
        'code' => 'TST',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function orderTestSector(City $city): Sector
{
    return Sector::query()->create([
        'city_id' => $city->id,
        'name' => 'Test Sector',
        'delivery_price' => 25.00,
        'is_active' => true,
    ]);
}

function orderTestOrder(User $seller, City $city, Sector $sector, OrderStatus $status = OrderStatus::CREATED): Order
{
    $order = Order::query()->create([
        'tracking_number' => 'TST-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'customer_first_name' => 'John',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000000',
        'customer_address' => '123 Test Street',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 100,
        'delivery_price' => 25,
        'status' => $status->value,
    ]);

    $order->recordStatus($status, $seller, 'Test seed status.');

    return $order->fresh(['city', 'sector', 'seller']);
}

test('seller can update own order when status is created', function () {
    $seller = orderTestUser(Role::SELLER);
    $city = orderTestCity();
    $sector = orderTestSector($city);
    $order = orderTestOrder($seller, $city, $sector, OrderStatus::CREATED);

    Sanctum::actingAs($seller);

    $response = $this->putJson(route('api.orders.update', $order), [
        'customer_phone' => '0611111111',
    ]);

    $response->assertOk();
    expect($order->fresh()->customer_phone)->toBe('0611111111');
});

test('seller cannot update own order when status is not created', function () {
    $seller = orderTestUser(Role::SELLER);
    $city = orderTestCity();
    $sector = orderTestSector($city);
    $order = orderTestOrder($seller, $city, $sector, OrderStatus::WAITING_PICKUP);

    Sanctum::actingAs($seller);

    $response = $this->putJson(route('api.orders.update', $order), [
        'customer_phone' => '0611111111',
    ]);

    $response->assertForbidden();
    expect($order->fresh()->customer_phone)->toBe('0600000000');
});

test('seller cannot update another sellers order', function () {
    $seller = orderTestUser(Role::SELLER);
    $otherSeller = orderTestUser(Role::SELLER);
    $city = orderTestCity();
    $sector = orderTestSector($city);
    $order = orderTestOrder($otherSeller, $city, $sector, OrderStatus::CREATED);

    Sanctum::actingAs($seller);

    $response = $this->putJson(route('api.orders.update', $order), [
        'customer_phone' => '0611111111',
    ]);

    $response->assertForbidden();
});

test('admin can update order regardless of status', function () {
    $admin = orderTestUser(Role::ADMIN);
    $seller = orderTestUser(Role::SELLER);
    $city = orderTestCity();
    $sector = orderTestSector($city);
    $order = orderTestOrder($seller, $city, $sector, OrderStatus::IN_TRANSIT);

    Sanctum::actingAs($admin);

    $response = $this->putJson(route('api.orders.update', $order), [
        'customer_phone' => '0622222222',
    ]);

    $response->assertOk();
    expect($order->fresh()->customer_phone)->toBe('0622222222');
});

test('order update records change history for modified fields only', function () {
    $admin = orderTestUser(Role::ADMIN);
    $seller = orderTestUser(Role::SELLER);
    $city = orderTestCity();
    $sector = orderTestSector($city);
    $order = orderTestOrder($seller, $city, $sector, OrderStatus::CREATED);

    Sanctum::actingAs($admin);

    $this->putJson(route('api.orders.update', $order), [
        'customer_phone' => '0611111111',
        'customer_first_name' => 'John',
    ]);

    $histories = OrderChangeHistory::query()
        ->where('order_id', $order->id)
        ->get();

    expect($histories)->toHaveCount(1);
    expect($histories->first()->field_name)->toBe('customer_phone');
    expect($histories->first()->old_value)->toBe('0600000000');
    expect($histories->first()->new_value)->toBe('0611111111');
    expect($histories->first()->changed_by)->toBe($admin->id);
});

test('order update records multiple field changes', function () {
    $admin = orderTestUser(Role::ADMIN);
    $seller = orderTestUser(Role::SELLER);
    $city = orderTestCity();
    $sector = orderTestSector($city);
    $order = orderTestOrder($seller, $city, $sector, OrderStatus::CREATED);

    Sanctum::actingAs($admin);

    $this->putJson(route('api.orders.update', $order), [
        'customer_phone' => '0611111111',
        'is_fragile' => true,
        'notes' => 'Handle carefully',
    ]);

    $histories = OrderChangeHistory::query()
        ->where('order_id', $order->id)
        ->orderBy('field_name')
        ->get();

    expect($histories)->toHaveCount(3);

    $fields = $histories->pluck('field_name')->all();
    expect($fields)->toContain('customer_phone', 'is_fragile', 'notes');

    $fragileHistory = $histories->firstWhere('field_name', 'is_fragile');
    expect($fragileHistory->old_value)->toBe('No');
    expect($fragileHistory->new_value)->toBe('Yes');
});

test('order show includes change history', function () {
    $admin = orderTestUser(Role::ADMIN);
    $seller = orderTestUser(Role::SELLER);
    $city = orderTestCity();
    $sector = orderTestSector($city);
    $order = orderTestOrder($seller, $city, $sector, OrderStatus::CREATED);

    OrderChangeHistory::query()->create([
        'order_id' => $order->id,
        'changed_by' => $admin->id,
        'field_name' => 'customer_phone',
        'old_value' => '0600000000',
        'new_value' => '0611111111',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson(route('api.orders.show', $order));

    $response->assertOk();
    $response->assertJsonPath('data.change_history.0.field_name', 'customer_phone');
    $response->assertJsonPath('data.change_history.0.old_value', '0600000000');
    $response->assertJsonPath('data.change_history.0.new_value', '0611111111');
    $response->assertJsonPath('data.change_history.0.changed_by.name', $admin->full_name);
});
