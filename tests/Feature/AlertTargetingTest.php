<?php

use App\Models\Alert;
use App\Models\City;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use App\Services\AlertService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
});

function alertUser(string $roleName, array $attributes = []): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create([...$attributes, 'role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

function alertCity(string $name): City
{
    return City::query()->create([
        'name' => $name,
        'code' => strtoupper(substr($name, 0, 3)),
        'region' => 'Region',
        'is_active' => true,
    ]);
}

/** Alerts on air for this user, by title, ignoring the banner/modal split. */
function visibleTitles(User $user): array
{
    return app(AlertService::class)->visibleTo($user)->pluck('title')->sort()->values()->all();
}

it('shows an alert addressed to everyone to everyone', function () {
    Alert::factory()->create(['title' => 'Maintenance']);

    expect(visibleTitles(alertUser(Role::SELLER)))->toBe(['Maintenance'])
        ->and(visibleTitles(alertUser(Role::DRIVER)))->toBe(['Maintenance']);
});

it('hides alerts that are expired or switched off', function () {
    Alert::factory()->expired()->create(['title' => 'Expired']);
    Alert::factory()->disabled()->create(['title' => 'Disabled']);
    Alert::factory()->create(['title' => 'Live']);

    expect(visibleTitles(alertUser(Role::SELLER)))->toBe(['Live']);
});

it('narrows the audience to the selected roles', function () {
    Alert::factory()->forRoles([Role::DRIVER])->create(['title' => 'Drivers only']);

    expect(visibleTitles(alertUser(Role::DRIVER)))->toBe(['Drivers only'])
        ->and(visibleTitles(alertUser(Role::SELLER)))->toBe([]);
});

it('treats roles and cities as a narrowing pair, not as alternatives', function () {
    $tangier = alertCity('Tanger');
    $casablanca = alertCity('Casablanca');

    Alert::factory()
        ->forRoles([Role::SELLER])
        ->forCities([$tangier->id])
        ->create(['title' => 'Sellers in Tangier']);

    $sellerInTangier = alertUser(Role::SELLER, ['city_id' => $tangier->id]);
    $sellerElsewhere = alertUser(Role::SELLER, ['city_id' => $casablanca->id]);
    $driverInTangier = alertUser(Role::DRIVER, ['city_id' => $tangier->id]);

    expect(visibleTitles($sellerInTangier))->toBe(['Sellers in Tangier']);

    // A seller from another city and a driver from Tangier each match one half
    // of the pair. Under an OR reading both would receive it, which is exactly
    // the blast radius this rule exists to prevent.
    expect(visibleTitles($sellerElsewhere))->toBe([])
        ->and(visibleTitles($driverInTangier))->toBe([]);
});

it('reaches a driver through the city of an assigned sector', function () {
    $tangier = alertCity('Tanger');
    $sector = Sector::query()->create([
        'city_id' => $tangier->id,
        'name' => 'Centre',
        'delivery_price' => 20,
        'is_active' => true,
    ]);

    // Drivers routinely have no city on their profile; theirs comes from the
    // sectors they cover.
    $driver = alertUser(Role::DRIVER, ['city_id' => null]);
    $driver->sectors()->attach($sector->id);

    Alert::factory()->forRoles([Role::DRIVER])->forCities([$tangier->id])->create(['title' => 'Tangier round']);

    expect(visibleTitles($driver->fresh(['roles'])))->toBe(['Tangier round']);
});

it('reaches a seller through the city of one of their shops', function () {
    $tangier = alertCity('Tanger');
    $casablanca = alertCity('Casablanca');

    $seller = alertUser(Role::SELLER, ['city_id' => $casablanca->id]);
    $store = Store::query()->create([
        'owner_id' => $seller->id,
        'name' => 'Boutique Tanger',
        'city_id' => $tangier->id,
        'is_active' => true,
        'is_default' => true,
    ]);
    $seller->stores()->attach($store->id);

    Alert::factory()->forRoles([Role::SELLER])->forCities([$tangier->id])->create(['title' => 'Tangier shops']);

    expect(visibleTitles($seller->fresh(['roles'])))->toBe(['Tangier shops']);
});

it('delivers to named people on top of the broadcast audience', function () {
    $tangier = alertCity('Tanger');
    $sellerInTangier = alertUser(Role::SELLER, ['city_id' => $tangier->id]);
    $guest = alertUser(Role::DISPATCHER, ['city_id' => null]);

    Alert::factory()
        ->forRoles([Role::SELLER])
        ->forCities([$tangier->id])
        ->create(['title' => 'Briefing', 'target_user_ids' => [$guest->id]]);

    expect(visibleTitles($sellerInTangier))->toBe(['Briefing'])
        ->and(visibleTitles($guest))->toBe(['Briefing']);
});

it('keeps an alert aimed at named people away from everyone else', function () {
    $chosen = alertUser(Role::SELLER);
    $other = alertUser(Role::SELLER);

    Alert::factory()->forUsers([$chosen->id])->create(['title' => 'Just for you']);

    expect(visibleTitles($chosen))->toBe(['Just for you'])
        ->and(visibleTitles($other))->toBe([]);
});

it('splits what it finds into banners and modals', function () {
    Alert::factory()->create(['title' => 'Banner']);
    Alert::factory()->modal()->create(['title' => 'Modal']);

    $resolved = app(AlertService::class)->forUser(alertUser(Role::SELLER));

    expect(array_column($resolved['banners'], 'title'))->toBe(['Banner'])
        ->and(array_column($resolved['modals'], 'title'))->toBe(['Modal']);
});
