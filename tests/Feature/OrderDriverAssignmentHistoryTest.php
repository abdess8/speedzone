<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderChangeHistory;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\OrderAuditService;
use App\Services\OrderDriverAssignmentService;
use App\Services\OrderDriverAutoAssignmentService;
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

function driverHistoryUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function driverHistoryCity(): City
{
    return City::query()->create([
        'name' => 'Driver History City',
        'code' => 'DHC',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function driverHistorySector(City $city): Sector
{
    return Sector::query()->create([
        'city_id' => $city->id,
        'name' => 'Driver History Sector',
        'delivery_price' => 25.00,
        'is_active' => true,
    ]);
}

function driverHistoryOrder(User $seller, City $city, Sector $sector, OrderStatus $status = OrderStatus::OUT_FOR_DELIVERY): Order
{
    $order = Order::query()->create([
        'tracking_number' => 'DHA-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
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

    return $order->fresh();
}

test('manual driver assignment records change history', function () {
    $admin = driverHistoryUser(Role::ADMIN);
    $seller = driverHistoryUser(Role::SELLER);
    $driver = driverHistoryUser(Role::DRIVER);
    $city = driverHistoryCity();
    $sector = driverHistorySector($city);
    $order = driverHistoryOrder($seller, $city, $sector);

    app(OrderDriverAssignmentService::class)->assign($order, $driver, $admin);

    $history = OrderChangeHistory::query()
        ->where('order_id', $order->id)
        ->first();

    expect($history)->not->toBeNull();
    expect($history->field_name)->toBe(OrderAuditService::DRIVER_ASSIGNMENT_MANUAL);
    expect($history->old_value)->toBeNull();
    expect($history->new_value)->toBe($driver->full_name);
    expect($history->changed_by)->toBe($admin->id);
});

test('manual driver reassignment records previous driver in change history', function () {
    $admin = driverHistoryUser(Role::ADMIN);
    $seller = driverHistoryUser(Role::SELLER);
    $firstDriver = driverHistoryUser(Role::DRIVER);
    $secondDriver = driverHistoryUser(Role::DRIVER);
    $city = driverHistoryCity();
    $sector = driverHistorySector($city);
    $order = driverHistoryOrder($seller, $city, $sector);

    $service = app(OrderDriverAssignmentService::class);
    $service->assign($order, $firstDriver, $admin);
    $service->assign($order->fresh(), $secondDriver, $admin);

    $history = OrderChangeHistory::query()
        ->where('order_id', $order->id)
        ->orderByDesc('id')
        ->first();

    expect($history->field_name)->toBe(OrderAuditService::DRIVER_ASSIGNMENT_MANUAL);
    expect($history->old_value)->toBe($firstDriver->full_name);
    expect($history->new_value)->toBe($secondDriver->full_name);
});

test('automatic driver assignment records change history without actor', function () {
    $seller = driverHistoryUser(Role::SELLER);
    $driver = driverHistoryUser(Role::DRIVER);
    $city = driverHistoryCity();
    $sector = driverHistorySector($city);
    $driver->sectors()->sync([$sector->id]);
    $order = driverHistoryOrder($seller, $city, $sector, OrderStatus::RECEIVED_IN_DESTINATION);

    app(OrderDriverAutoAssignmentService::class)->assignBySector($order);

    $history = OrderChangeHistory::query()
        ->where('order_id', $order->id)
        ->first();

    expect($history)->not->toBeNull();
    expect($history->field_name)->toBe(OrderAuditService::DRIVER_ASSIGNMENT_AUTO);
    expect($history->old_value)->toBeNull();
    expect($history->new_value)->toBe($driver->full_name);
    expect($history->changed_by)->toBeNull();
});
