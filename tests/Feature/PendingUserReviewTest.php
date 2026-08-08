<?php

use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function reviewCity(): City
{
    return City::query()->firstOrCreate(
        ['code' => 'REV'],
        ['name' => 'Review City', 'region' => 'Test', 'is_active' => true]
    );
}

/**
 * A reviewer holds exactly the grants named — never `users.roles.assign` or
 * `users.update` unless the test asks for them.
 *
 * @param  array<int, string>  $permissions
 */
function reviewer(array $permissions = ['users.read', 'roles.read']): User
{
    $role = Role::query()->create([
        'name' => 'reviewer.'.Str::random(8),
        'label' => 'Reviewer',
    ]);

    $role->permissions()->sync(
        Permission::query()->whereIn('name', $permissions)->pluck('id')
    );

    $user = User::factory()->create([
        'status' => UserStatus::Active,
        'city_id' => reviewCity()->id,
        'role_id' => $role->id,
    ]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function pendingSeller(): User
{
    $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

    $user = User::factory()->create([
        'status' => UserStatus::PendingApproval,
        'city_id' => reviewCity()->id,
        'role_id' => $sellerRole->id,
        'email_verified_at' => now(),
    ]);
    $user->roles()->sync([$sellerRole->id]);

    return $user->fresh(['roles']);
}

function reviewPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'first_name' => $user->first_name ?? 'Amine',
        'last_name' => $user->last_name ?? 'Benali',
        'email' => $user->email,
        'role_id' => $user->role_id,
        'city_id' => $user->city_id,
    ], $overrides);
}

test('a reviewer without the role grant cannot change the role of a pending account', function () {
    $seller = pendingSeller();
    $adminRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();

    $this->actingAs(reviewer())
        ->put(route('admin.pending-users.update', $seller), reviewPayload($seller, [
            'role_id' => $adminRole->id,
        ]))
        ->assertForbidden();

    expect($seller->fresh()->role_id)->not->toBe($adminRole->id)
        ->and($seller->fresh(['roles'])->roles->pluck('name')->all())->toBe([Role::SELLER]);
});

test('a reviewer without the role grant can still correct the other details', function () {
    $seller = pendingSeller();

    $this->actingAs(reviewer())
        ->put(route('admin.pending-users.update', $seller), reviewPayload($seller, [
            'first_name' => 'Corrected',
            'phone_number' => '+212600000001',
        ]))
        ->assertRedirect(route('admin.pending-users.show', $seller));

    expect($seller->fresh()->first_name)->toBe('Corrected');
});

test('a reviewer holding the role grant can change the role', function () {
    $seller = pendingSeller();
    $adminRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();

    $this->actingAs(reviewer(['users.read', 'roles.read', 'users.roles.assign']))
        ->put(route('admin.pending-users.update', $seller), reviewPayload($seller, [
            'role_id' => $adminRole->id,
        ]))
        ->assertRedirect();

    expect($seller->fresh(['roles'])->roles->pluck('name')->all())->toBe([Role::ADMIN]);
});

test('changing the e-mail address drops the verification', function () {
    $seller = pendingSeller();

    $this->actingAs(reviewer())
        ->put(route('admin.pending-users.update', $seller), reviewPayload($seller, [
            'email' => 'moved@example.com',
        ]))
        ->assertRedirect();

    expect($seller->fresh()->email_verified_at)->toBeNull();
});

test('a reviewer without the update grant cannot set a password', function () {
    $seller = pendingSeller();
    $before = $seller->password;

    $this->actingAs(reviewer())
        ->put(route('admin.pending-users.password.update', $seller), [
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertForbidden();

    expect($seller->fresh()->password)->toBe($before);
});

test('a reviewer holding the update grant can set a password', function () {
    $seller = pendingSeller();

    $this->actingAs(reviewer(['users.read', 'roles.read', 'users.update']))
        ->put(route('admin.pending-users.password.update', $seller), [
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertRedirect();

    expect(Hash::check('Password123!', $seller->fresh()->password))->toBeTrue();
});

test('an account that is not under review is out of reach', function () {
    $active = pendingSeller();
    $active->forceFill(['status' => UserStatus::Active])->save();

    $this->actingAs(reviewer())
        ->get(route('admin.pending-users.show', $active))
        ->assertNotFound();
});
