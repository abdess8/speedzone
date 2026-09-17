<?php

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\LoginRedirect;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function loginRedirectAdmin(): User
{
    $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function loginRedirectSeller(array $overrides = []): User
{
    $role = Role::query()->where('name', Role::SELLER)->firstOrFail();
    $user = User::factory()->create(array_merge([
        'email' => 'redirect-seller@example.test',
        'password' => Hash::make('Password123!'),
        'role_id' => $role->id,
        'status' => UserStatus::Active,
        'email_verified_at' => now(),
    ], $overrides));
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles']);
}

it('lets an admin pick the post-login page on a role', function () {
    $admin = loginRedirectAdmin();
    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('roles.edit', $sellerRole))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/edit')
            ->has('loginRedirects')
            ->where('role.login_redirect', null)
        );

    $this->actingAs($admin)
        ->from(route('roles.edit', $sellerRole))
        ->put(route('roles.update', $sellerRole), [
            'name' => Role::SELLER,
            'login_redirect' => 'orders',
            'permission_ids' => $sellerRole->permissions()->pluck('permissions.id')->all(),
        ])
        ->assertRedirect(route('roles.index'));

    expect($sellerRole->fresh()->login_redirect)->toBe('orders');
});

it('rejects a redirect that is not in the catalogue', function () {
    $admin = loginRedirectAdmin();
    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $this->actingAs($admin)
        ->from(route('roles.edit', $sellerRole))
        ->put(route('roles.update', $sellerRole), [
            'name' => Role::SELLER,
            'login_redirect' => 'https://evil.test',
            'permission_ids' => $sellerRole->permissions()->pluck('permissions.id')->all(),
        ])
        ->assertRedirect(route('roles.edit', $sellerRole))
        ->assertSessionHasErrors('login_redirect');
});

it('sends an active seller to the page chosen on their role', function () {
    Role::query()->where('name', Role::SELLER)->update([
        'login_redirect' => 'orders',
    ]);

    loginRedirectSeller();

    $this->post('/login', [
        'email' => 'redirect-seller@example.test',
        'password' => 'Password123!',
    ])
        ->assertRedirect(route('orders.index'));
});

it('keeps pending sellers on the approval screen even when a redirect is set', function () {
    Role::query()->where('name', Role::SELLER)->update([
        'login_redirect' => 'orders',
    ]);

    loginRedirectSeller([
        'email' => 'pending-redirect@example.test',
        'status' => UserStatus::PendingApproval,
    ]);

    $this->post('/login', [
        'email' => 'pending-redirect@example.test',
        'password' => 'Password123!',
    ])
        ->assertRedirect(route('account.pending-approval'));
});

it('falls back to the seller dashboard when no redirect is set', function () {
    expect(LoginRedirect::keyForUser(loginRedirectSeller([
        'email' => 'default-seller@example.test',
    ])))->toBeNull();

    $this->post('/login', [
        'email' => 'default-seller@example.test',
        'password' => 'Password123!',
    ])
        ->assertRedirect(route('dashboard.seller'));
});
