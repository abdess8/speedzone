<?php

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
