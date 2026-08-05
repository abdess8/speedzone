<?php

use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
});

test('scratch: the web form payload as the browser actually sends it', function () {
    $role = Role::query()->where('name', Role::SELLER)->firstOrFail();
    $seller = User::factory()->create(['role_id' => $role->id]);
    $seller->roles()->sync([$role->id]);
    $seller = $seller->fresh(['roles.permissions']);

    $city = City::query()->create(['name' => 'Fes', 'code' => 'FES', 'region' => 'R', 'is_active' => true]);
    $sector = Sector::query()->create([
        'city_id' => $city->id, 'name' => 'Sidi Brahim',
        'delivery_price' => 40, 'return_price' => 15, 'is_active' => true,
    ]);

    // Exactly the keys emptyForm() posts, empty strings included.
    $response = $this->actingAs($seller)->post(route('orders.store'), [
        'customer_first_name' => 'ayoub',
        'customer_last_name' => 'outiti',
        'customer_phone' => '0649440905',
        'customer_address' => 'SidiDriss Lots 6 appt 36',
        'city_id' => $city->id,
        'sector_id' => $sector->id,
        'payment_method' => 'CASH',
        'order_amount' => '388',
        'order_value' => '',
        'delivery_price' => '40',
        'delivery_included' => true,
        'notes' => '',
        'is_fragile' => false,
        'can_be_opened' => false,
        'option_exchange' => false,
        'items' => [],
        'discount_amount' => '',
    ]);

    $response->assertRedirect();

    $order = Order::query()->firstOrFail();
    dump([
        'discount_amount' => $order->discount_amount,
        'total_amount' => $order->total_amount,
        'delivery_included' => $order->delivery_included,
    ]);
});
