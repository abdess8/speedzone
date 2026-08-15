<?php

use App\Models\City;
use App\Models\Permission;
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

    $city = City::query()->create([
        'name' => 'Payout City',
        'code' => 'PYC',
        'region' => 'Test',
        'is_active' => true,
    ]);

    $this->sector = Sector::query()->create([
        'city_id' => $city->id,
        'name' => 'Confidential',
        'delivery_price' => 30,
        'return_price' => 15,
        'delivery_driver_price' => 12,
        'is_active' => true,
    ]);
});

function payoutUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

it('never puts the driver payout in a seller payload', function () {
    $this->actingAs(payoutUser(Role::SELLER))
        ->get(route('sectors.show', $this->sector))
        ->assertInertia(function ($page) {
            $sector = $page->toArray()['props']['sector'];

            expect($sector)->not->toHaveKey('delivery_driver_price')
                ->and($sector['delivery_price'])->toEqual(30);
        });
});

it('withholds it from the seller api too', function () {
    Sanctum::actingAs(payoutUser(Role::SELLER));

    $this->getJson('/api/sectors/'.$this->sector->id)
        ->assertOk()
        ->assertJsonMissingPath('data.delivery_driver_price');
});

it('still shows it to administration', function () {
    $this->actingAs(payoutUser(Role::ADMIN))
        ->get(route('sectors.show', $this->sector))
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['sector']['delivery_driver_price'])->toEqual(12)
                ->and($props['can']['view_driver_price'])->toBeTrue();
        });
});

it('ignores a payout submitted by somebody who cannot read it', function () {
    $dispatcher = payoutUser(Role::DISPATCHER);
    $dispatcher->roles->first()->permissions()->syncWithoutDetaching(
        Permission::query()->where('name', 'sectors.update')->pluck('id')->all()
    );

    $this->actingAs($dispatcher->fresh(['roles.permissions']))
        ->put(route('sectors.update', $this->sector), [
            'name' => 'Confidential',
            'delivery_price' => 30,
            'delivery_driver_price' => 999,
        ])
        ->assertRedirect();

    expect((float) $this->sector->fresh()->delivery_driver_price)->toEqual(12);
});
