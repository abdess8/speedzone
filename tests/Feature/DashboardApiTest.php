<?php

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

function dashboardUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

test('dashboard api returns logistics data for authorized admin', function () {
    $admin = dashboardUser(Role::ADMIN);

    $response = $this->actingAs($admin)->getJson('/api/dashboard?period=last_30_days');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'summary' => [
                    'orders_today',
                    'delivered_orders',
                    'revenue_in_period',
                ],
                'charts' => [
                    'ordersByDay',
                    'ordersByStatus',
                    'ordersByCity',
                    'monthlyRevenue',
                    'deliverySuccessRate',
                    'ordersPerSeller',
                    'deliveryAgentsPerformance',
                ],
                'recentOrders',
                'recentActivities',
                'topCustomers',
                'topCities',
                'paymentMethods',
                'deliveryPerformance',
                'meta',
                'limitations',
            ],
        ]);
});

test('dashboard api rejects users without order read permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/dashboard')
        ->assertForbidden();
});

test('dashboard api validates custom period requires dates', function () {
    $admin = dashboardUser(Role::ADMIN);

    $this->actingAs($admin)->getJson('/api/dashboard?period=custom')
        ->assertStatus(422);
});

test('dashboard api defaults to all time and includes orders older than 30 days', function () {
    $admin = dashboardUser(Role::ADMIN);
    $seller = dashboardUser(Role::SELLER);
    $city = City::query()->create([
        'name' => 'Dashboard City',
        'code' => 'DSH',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $payload = [
        'seller_id' => $seller->id,
        'customer_first_name' => 'Ali',
        'customer_last_name' => 'Test',
        'customer_phone' => '0600000001',
        'customer_address' => '1 Test Street',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 100,
        'delivery_price' => 25,
        'status' => OrderStatus::CREATED->value,
    ];

    $old = Order::query()->create(array_merge($payload, [
        'tracking_number' => 'DSH-OLD-000001',
    ]));
    $old->forceFill([
        'created_at' => now()->subDays(90),
        'updated_at' => now()->subDays(90),
    ])->saveQuietly();

    Order::query()->create(array_merge($payload, [
        'tracking_number' => 'DSH-NEW-000001',
        'customer_phone' => '0600000002',
    ]));

    $this->actingAs($admin)->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.meta.filter.period', 'all_time')
        ->assertJsonPath('data.summary.orders_in_period', 2);

    $this->actingAs($admin)->getJson('/api/dashboard?period=last_30_days')
        ->assertOk()
        ->assertJsonPath('data.summary.orders_in_period', 1);
});
