<?php

use App\Enums\UserStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\TeamRoleService;
use App\Services\TeamService;
use App\Support\RolePermissionMatrix;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function teamVendor(): User
{
    $role = Role::query()->system()->where('name', Role::SELLER)->firstOrFail();

    $vendor = User::factory()->create(['role_id' => $role->id]);
    $vendor->roles()->sync([$role->id]);

    return $vendor->fresh(['roles.permissions']);
}

function teamStore(User $owner, string $name): Store
{
    $store = Store::query()->create([
        'owner_id' => $owner->id,
        'name' => $name,
        'is_default' => ! Store::query()->where('owner_id', $owner->id)->exists(),
        'is_active' => true,
    ]);

    $store->users()->syncWithoutDetaching([$owner->id]);

    return $store;
}

/**
 * @param  array<int, string>  $permissions
 */
function teamRole(User $owner, string $label, array $permissions): Role
{
    return app(TeamRoleService::class)->create($owner, $label, $permissions);
}

it('creates a member bound to the vendor account, his stores and his roles', function () {
    $vendor = teamVendor();
    $store = teamStore($vendor, 'Boutique A');
    $other = teamStore($vendor, 'Boutique B');
    $role = teamRole($vendor, 'Préparateur', ['orders.read.own', 'orders.print']);

    $member = app(TeamService::class)->create($vendor, [
        'first_name' => 'Yassine',
        'last_name' => 'B.',
        'email' => 'yassine@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    expect($member->parent_user_id)->toBe($vendor->id)
        ->and($member->accountOwnerId())->toBe($vendor->id)
        ->and($member->status)->toBe(UserStatus::Active)
        ->and($member->stores->pluck('id')->all())->toBe([$store->id])
        ->and($member->stores->pluck('id')->all())->not->toContain($other->id)
        ->and($member->roles->pluck('id')->all())->toBe([$role->id]);
});

it('never grants a custom role a permission the vendor cannot delegate', function () {
    $vendor = teamVendor();

    $role = teamRole($vendor, 'Trop puissant', [
        'orders.read.own',
        // Store and team administration are excluded from the ceiling.
        'stores.create',
        'team.create',
        // Not a seller permission at all.
        'orders.read.all',
        'users.delete',
    ]);

    expect($role->permissions->pluck('name')->all())->toBe(['orders.read.own']);
});

it('exposes only the seller ceiling in the role editor', function () {
    $vendor = teamVendor();

    $offered = collect(app(TeamRoleService::class)->permissionOptions())
        ->flatMap(fn (array $group) => array_column($group['permissions'], 'name'))
        ->all();

    expect($offered)->not->toContain('stores.create')
        ->and($offered)->not->toContain('team.create')
        ->and($offered)->not->toContain('orders.read.all')
        ->and($offered)->toContain('orders.read.own');

    $this->actingAs($vendor)
        ->get(route('team.roles.create'))
        ->assertOk();
});

it('keeps a member out of the team screens', function () {
    $vendor = teamVendor();
    $store = teamStore($vendor, 'Boutique A');
    $role = teamRole($vendor, 'Préparateur', ['orders.read.own']);

    $member = app(TeamService::class)->create($vendor, [
        'first_name' => 'Sara',
        'last_name' => 'K.',
        'email' => 'sara@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    $this->actingAs($member->fresh(['roles.permissions']))
        ->get(route('team.index'))
        ->assertForbidden();
});

it('refuses to attach a store or a role belonging to another vendor', function () {
    $vendor = teamVendor();
    $intruder = teamVendor();

    $ownStore = teamStore($vendor, 'Boutique A');
    $foreignStore = teamStore($intruder, 'Boutique pirate');
    $ownRole = teamRole($vendor, 'Préparateur', ['orders.read.own']);
    $foreignRole = teamRole($intruder, 'Pirate', ['orders.read.own']);

    $member = app(TeamService::class)->create($vendor, [
        'first_name' => 'Omar',
        'last_name' => 'L.',
        'email' => 'omar@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$ownStore->id, $foreignStore->id],
        'role_ids' => [$ownRole->id, $foreignRole->id],
    ]);

    expect($member->stores->pluck('id')->all())->toBe([$ownStore->id])
        ->and($member->roles->pluck('id')->all())->toBe([$ownRole->id]);
});

it('destroys the live sessions of a suspended member', function () {
    $vendor = teamVendor();
    $store = teamStore($vendor, 'Boutique A');
    $role = teamRole($vendor, 'Préparateur', ['orders.read.own']);

    $member = app(TeamService::class)->create($vendor, [
        'first_name' => 'Nadia',
        'last_name' => 'M.',
        'email' => 'nadia@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    // The suite runs on the array session driver; revocation only has anything
    // to purge under the database driver the app actually uses.
    config(['session.driver' => 'database']);

    DB::table('sessions')->insert([
        'id' => 'session-under-test',
        'user_id' => $member->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'phpunit',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $destroyed = app(TeamService::class)->suspend($vendor, $member);

    expect($destroyed)->toBe(1)
        ->and($member->fresh()->status)->toBe(UserStatus::Suspended)
        ->and(DB::table('sessions')->where('user_id', $member->id)->exists())->toBeFalse();
});

it('denies login to a suspended member', function () {
    $vendor = teamVendor();
    $store = teamStore($vendor, 'Boutique A');
    $role = teamRole($vendor, 'Préparateur', ['orders.read.own']);

    $member = app(TeamService::class)->create($vendor, [
        'first_name' => 'Karim',
        'last_name' => 'T.',
        'email' => 'karim@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    app(TeamService::class)->suspend($vendor, $member);

    $this->post(route('login'), [
        'email' => 'karim@example.test',
        'password' => 'Secret!12345',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('lets the vendor reactivate a suspended member', function () {
    $vendor = teamVendor();
    $store = teamStore($vendor, 'Boutique A');
    $role = teamRole($vendor, 'Préparateur', ['orders.read.own']);

    $member = app(TeamService::class)->create($vendor, [
        'first_name' => 'Hind',
        'last_name' => 'R.',
        'email' => 'hind@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    app(TeamService::class)->suspend($vendor, $member);

    $this->actingAs($vendor)
        ->put(route('team.reactivate', $member->id))
        ->assertRedirect();

    expect($member->fresh()->status)->toBe(UserStatus::Active);
});

it('refuses to touch a member of another vendor', function () {
    $vendor = teamVendor();
    $intruder = teamVendor();
    $store = teamStore($vendor, 'Boutique A');
    $role = teamRole($vendor, 'Préparateur', ['orders.read.own']);

    $member = app(TeamService::class)->create($vendor, [
        'first_name' => 'Zineb',
        'last_name' => 'A.',
        'email' => 'zineb@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    $this->actingAs($intruder)
        ->put(route('team.suspend', $member->id))
        ->assertForbidden();

    expect($member->fresh()->status)->toBe(UserStatus::Active);
});

it('keeps a vendor role from shadowing a platform role', function () {
    $vendor = teamVendor();

    $role = teamRole($vendor, 'Seller', ['orders.read.own']);

    expect($role->name)->toStartWith(Role::VENDOR_PREFIX)
        ->and($role->label)->toBe('Seller')
        // The lookup used everywhere else must still find the platform role.
        ->and(Role::query()->where('name', Role::SELLER)->count())->toBe(1);
});

it('blocks deletion of a role still held by a member', function () {
    $vendor = teamVendor();
    $store = teamStore($vendor, 'Boutique A');
    $role = teamRole($vendor, 'Préparateur', ['orders.read.own']);

    app(TeamService::class)->create($vendor, [
        'first_name' => 'Ilyas',
        'last_name' => 'D.',
        'email' => 'ilyas@example.test',
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);

    $this->actingAs($vendor)
        ->delete(route('team.roles.destroy', $role->id))
        ->assertSessionHasErrors('role');

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('grants the seller role every permission of its own ceiling', function () {
    $missing = array_diff(
        RolePermissionMatrix::sellerCeiling(),
        Permission::query()->pluck('name')->all()
    );

    expect($missing)->toBe([]);
});
