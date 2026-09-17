<?php

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\TeamRoleService;
use App\Services\TeamService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;
use Tests\Support\StockFixtures;

beforeEach(function () {
    $this->withoutVite();

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function directoryAdmin(): User
{
    return StockFixtures::user(Role::ADMIN);
}

function directorySeller(string $email = 'seller@example.test'): User
{
    $seller = StockFixtures::user(Role::SELLER);
    $seller->forceFill(['email' => $email])->save();

    return $seller->fresh(['roles.permissions']);
}

function directoryStore(User $owner, string $name): Store
{
    return StockFixtures::store($owner, $name);
}

function directoryRole(User $owner, string $label = 'Préparateur'): Role
{
    return app(TeamRoleService::class)->create($owner, $label, ['orders.read.own']);
}

function directoryMember(User $owner, Store $store, Role $role, string $email): User
{
    return app(TeamService::class)->create($owner, [
        'first_name' => 'Yassine',
        'last_name' => 'B.',
        'email' => $email,
        'password' => 'Secret!12345',
        'store_ids' => [$store->id],
        'role_ids' => [$role->id],
    ]);
}

it('shows the admin every vendor store instead of an empty personal list', function () {
    $admin = directoryAdmin();
    $alice = directorySeller('alice@example.test');
    $bob = directorySeller('bob@example.test');
    directoryStore($alice, 'Boutique Alice');
    directoryStore($bob, 'Boutique Bob');

    $this->actingAs($admin)
        ->get(route('stores.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('stores/admin')
            ->has('stores.data', 2)
            ->where('stats.total', 2)
            ->where('stats.sellers', 2)
        );
});

it('still shows a seller only his own stores', function () {
    $alice = directorySeller('alice@example.test');
    $bob = directorySeller('bob@example.test');
    directoryStore($alice, 'Boutique Alice');
    directoryStore($bob, 'Boutique Bob');

    $this->actingAs($alice)
        ->get(route('stores.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('stores/index')
            ->has('stores', 1)
            ->where('stores.0.name', 'Boutique Alice')
        );
});

it('lets an admin create a store for a seller without attaching it to himself', function () {
    $admin = directoryAdmin();
    $seller = directorySeller();

    $this->actingAs($admin)
        ->post(route('stores.store'), [
            'seller_id' => $seller->id,
            'name' => 'Nova Cosmetics',
            'is_active' => true,
        ])
        ->assertRedirect(route('stores.index'))
        ->assertSessionHas('success');

    $store = Store::query()->where('name', 'Nova Cosmetics')->first();

    expect($store)->not->toBeNull()
        ->and($store->owner_id)->toBe($seller->id)
        ->and($store->users()->pluck('users.id')->all())->toBe([$seller->id])
        ->and($store->users()->pluck('users.id')->all())->not->toContain($admin->id);
});

it('refuses to create a store for the admin himself', function () {
    $admin = directoryAdmin();

    $this->actingAs($admin)
        ->from(route('stores.create'))
        ->post(route('stores.store'), [
            'name' => 'Admin Shop',
            'is_active' => true,
        ])
        ->assertRedirect(route('stores.create'))
        ->assertSessionHasErrors('seller_id');

    expect(Store::query()->where('name', 'Admin Shop')->exists())->toBeFalse()
        ->and(Store::query()->where('owner_id', $admin->id)->exists())->toBeFalse();
});

it('shows the admin every vendor teammate and lets him suspend one', function () {
    $admin = directoryAdmin();
    $alice = directorySeller('alice@example.test');
    $bob = directorySeller('bob@example.test');
    $aliceStore = directoryStore($alice, 'Alice Shop');
    $bobStore = directoryStore($bob, 'Bob Shop');
    $aliceRole = directoryRole($alice);
    $bobRole = directoryRole($bob);
    $aliceMember = directoryMember($alice, $aliceStore, $aliceRole, 'alice.member@example.test');
    directoryMember($bob, $bobStore, $bobRole, 'bob.member@example.test');

    $this->actingAs($admin)
        ->get(route('team.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('team/admin')
            ->has('members.data', 2)
            ->where('stats.total', 2)
        );

    $this->actingAs($admin)
        ->put(route('team.suspend', $aliceMember->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($aliceMember->fresh()->status)->toBe(UserStatus::Suspended);
});

it('lets an admin create a teammate on a seller account with that seller\'s shops and roles', function () {
    $admin = directoryAdmin();
    $seller = directorySeller();
    $store = directoryStore($seller, 'Main Shop');
    $role = directoryRole($seller);

    $this->actingAs($admin)
        ->post(route('team.store'), [
            'seller_id' => $seller->id,
            'first_name' => 'Sara',
            'last_name' => 'K.',
            'email' => 'sara@example.test',
            'password' => 'Secret!12345',
            'password_confirmation' => 'Secret!12345',
            'store_ids' => [$store->id],
            'role_ids' => [$role->id],
        ])
        ->assertRedirect(route('team.index'))
        ->assertSessionHas('success');

    $member = User::query()->where('email', 'sara@example.test')->first();

    expect($member)->not->toBeNull()
        ->and($member->parent_user_id)->toBe($seller->id)
        ->and($member->parent_user_id)->not->toBe($admin->id)
        ->and($member->stores->pluck('id')->all())->toBe([$store->id])
        ->and($member->roles->pluck('id')->all())->toBe([$role->id]);
});

it('still shows a seller only his own teammates', function () {
    $alice = directorySeller('alice@example.test');
    $bob = directorySeller('bob@example.test');
    $aliceStore = directoryStore($alice, 'Alice Shop');
    $bobStore = directoryStore($bob, 'Bob Shop');
    directoryMember($alice, $aliceStore, directoryRole($alice), 'alice.member@example.test');
    directoryMember($bob, $bobStore, directoryRole($bob), 'bob.member@example.test');

    $this->actingAs($alice)
        ->get(route('team.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('team/index')
            ->has('members', 1)
            ->where('members.0.email', 'alice.member@example.test')
        );
});

it('shows the admin every vendor team role and lets him create one for a seller', function () {
    $admin = directoryAdmin();
    $alice = directorySeller('alice@example.test');
    $bob = directorySeller('bob@example.test');
    directoryRole($alice, 'Préparateur Alice');
    directoryRole($bob, 'Préparateur Bob');

    $this->actingAs($admin)
        ->get(route('team.roles.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('team/roles/admin')
            ->has('roles.data', 2)
            ->where('stats.total', 2)
            ->where('stats.sellers', 2)
        );

    $this->actingAs($admin)
        ->post(route('team.roles.store'), [
            'seller_id' => $alice->id,
            'label' => 'Gestionnaire de stock',
            'permissions' => ['orders.read.own'],
        ])
        ->assertRedirect(route('team.roles.index'))
        ->assertSessionHas('success');

    $role = Role::query()->where('label', 'Gestionnaire de stock')->first();

    expect($role)->not->toBeNull()
        ->and($role->owner_id)->toBe($alice->id)
        ->and($role->owner_id)->not->toBe($admin->id)
        ->and($role->isCustom())->toBeTrue();
});

it('refuses to create a team role without a seller', function () {
    $admin = directoryAdmin();

    $this->actingAs($admin)
        ->from(route('team.roles.create'))
        ->post(route('team.roles.store'), [
            'label' => 'Rôle orphelin',
            'permissions' => ['orders.read.own'],
        ])
        ->assertRedirect(route('team.roles.create'))
        ->assertSessionHasErrors('seller_id');

    expect(Role::query()->where('label', 'Rôle orphelin')->exists())->toBeFalse()
        ->and(Role::query()->where('owner_id', $admin->id)->exists())->toBeFalse();
});

it('still shows a seller only his own team roles', function () {
    $alice = directorySeller('alice@example.test');
    $bob = directorySeller('bob@example.test');
    directoryRole($alice, 'Préparateur Alice');
    directoryRole($bob, 'Préparateur Bob');

    $this->actingAs($alice)
        ->get(route('team.roles.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('team/roles/index')
            ->has('roles', 1)
            ->where('roles.0.label', 'Préparateur Alice')
        );
});
