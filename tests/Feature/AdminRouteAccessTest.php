<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Regression cover for the route-level RBAC guard.
 *
 * User and role administration used to be reachable by any authenticated
 * account, which let a seller or a driver grant themselves every permission.
 */
beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function routeAccessUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

dataset('non_admin_roles', [
    'seller' => [Role::SELLER],
    'driver' => [Role::DRIVER],
]);

it('blocks non-admin roles from the user administration screens', function (string $roleName) {
    $user = routeAccessUser($roleName);

    $this->actingAs($user)->get('/users')->assertForbidden();
    $this->actingAs($user)->get('/users/create')->assertForbidden();
})->with('non_admin_roles');

it('blocks non-admin roles from the role administration screens', function (string $roleName) {
    $user = routeAccessUser($roleName);

    $this->actingAs($user)->get('/roles')->assertForbidden();
})->with('non_admin_roles');

it('stops a seller from creating a user through the store endpoint', function () {
    $seller = routeAccessUser(Role::SELLER);
    $adminRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();

    $this->actingAs($seller)
        ->post('/users', [
            'first_name' => 'Mallory',
            'last_name' => 'Escalation',
            'email' => 'mallory@example.test',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
            'role_id' => $adminRole->id,
        ])
        ->assertForbidden();

    expect(User::query()->where('email', 'mallory@example.test')->exists())->toBeFalse();
});

it('lets an admin reach the administration screens', function () {
    $admin = routeAccessUser(Role::ADMIN);

    $this->actingAs($admin)->get('/users')->assertOk();
    $this->actingAs($admin)->get('/roles')->assertOk();
});

it('refuses to delete a seeded default role', function () {
    $admin = routeAccessUser(Role::ADMIN);
    $driverRole = Role::query()->where('name', Role::DRIVER)->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $driverRole))
        ->assertForbidden();

    expect(Role::query()->whereKey($driverRole->id)->exists())->toBeTrue();
});

it('refuses to let a user delete their own account', function () {
    $admin = routeAccessUser(Role::ADMIN);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertForbidden();

    expect(User::query()->whereKey($admin->id)->exists())->toBeTrue();
});
