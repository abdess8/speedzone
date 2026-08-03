<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Guides\GuideAccess;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * Which roles are offered which guide.
 *
 * Two rules carry the feature. A guide assigned to nobody stays visible —
 * silence means "no restriction", so adding a guide and forgetting the grid
 * cannot make it disappear platform-wide. And the permission floor still wins:
 * ticking a box never hands someone a walkthrough of a screen they would be
 * bounced out of.
 */
beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    GuideAccess::forget();
});

function accessUserWithRole(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function assignGuide(string $guide, string $roleName): void
{
    DB::table('guide_role')->insert([
        'guide_key' => $guide,
        'role_id' => Role::query()->where('name', $roleName)->value('id'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    GuideAccess::forget();
}

test('a guide assigned to nobody stays offered to everyone who may follow it', function () {
    $this->actingAs(accessUserWithRole('Seller'))
        ->get(route('guides.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $keys = collect($page->toArray()['props']['guides'])->pluck('key');

            expect($keys)->toContain('orders-import');
        });
});

test('assigning a guide to another role withdraws it', function () {
    assignGuide('orders-import', 'Admin');

    $this->actingAs(accessUserWithRole('Seller'))
        ->get(route('guides.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $keys = collect($page->toArray()['props']['guides'])->pluck('key');

            expect($keys)->not->toContain('orders-import')
                // Untouched guides keep their "no opinion" state.
                ->and($keys)->toContain('orders-create');
        });
});

test('a team member follows the seller line of the grid', function () {
    assignGuide('orders-create', 'Seller');

    $owner = accessUserWithRole('Seller');

    $customRole = Role::create([
        'name' => 'vendor.'.$owner->id.'.entry',
        'label' => 'Order entry',
        'owner_id' => $owner->id,
    ]);
    $customRole->permissions()->sync(
        Permission::query()->where('name', 'orders.create')->pluck('id')
    );

    $member = User::factory()->create(['parent_user_id' => $owner->id, 'role_id' => $customRole->id]);
    $member->roles()->sync([$customRole->id]);

    $this->actingAs($member->fresh(['roles.permissions']))
        ->get(route('guides.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $keys = collect($page->toArray()['props']['guides'])->pluck('key');

            expect($keys)->toContain('orders-create');
        });
});

test('an assignment never lifts the permission floor', function () {
    assignGuide('orders-import', 'Driver');

    $this->actingAs(accessUserWithRole('Driver'))
        ->get(route('guides.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $keys = collect($page->toArray()['props']['guides'])->pluck('key');

            expect($keys)->not->toContain('orders-import');
        });
});

test('the grid screen lists every guide with its roles', function () {
    assignGuide('orders-import', 'Seller');

    $this->actingAs(accessUserWithRole('Admin'))
        ->get(route('roles.guides.edit'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('roles/guides')->where('can.update', true);

            $guides = collect($page->toArray()['props']['guides']);
            $sellerId = Role::query()->where('name', 'Seller')->value('id');

            expect($guides->pluck('key'))->toContain('orders-import', 'team-member')
                ->and($guides->firstWhere('key', 'orders-import')['role_ids'])->toBe([$sellerId])
                ->and($guides->firstWhere('key', 'team-member')['role_ids'])->toBe([]);
        });
});

test('saving the grid replaces the whole matrix', function () {
    assignGuide('orders-import', 'Admin');

    $sellerId = Role::query()->where('name', 'Seller')->value('id');

    $this->actingAs(accessUserWithRole('Admin'))
        ->put(route('roles.guides.update'), [
            'assignments' => ['orders-import' => [$sellerId]],
        ])
        ->assertRedirect();

    GuideAccess::forget();

    expect(GuideAccess::rolesFor('orders-import'))->toBe([$sellerId])
        // Guides absent from the payload are saved as empty, not left alone:
        // unchecking every box has to survive the round trip.
        ->and(GuideAccess::rolesFor('team-member'))->toBe([]);
});

test('a vendor role cannot be assigned from the platform grid', function () {
    $owner = accessUserWithRole('Seller');
    $vendorRole = Role::create([
        'name' => 'vendor.'.$owner->id.'.entry',
        'label' => 'Order entry',
        'owner_id' => $owner->id,
    ]);

    $this->actingAs(accessUserWithRole('Admin'))
        ->put(route('roles.guides.update'), [
            'assignments' => ['orders-import' => [$vendorRole->id]],
        ])
        ->assertSessionHasErrors('assignments.orders-import.0');
});

test('a reader without the roles permission cannot open the grid', function () {
    $this->actingAs(accessUserWithRole('Seller'))
        ->get(route('roles.guides.edit'))
        ->assertForbidden();
});
