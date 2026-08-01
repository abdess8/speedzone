<?php

use App\Models\Alert;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use App\Services\AlertService;
use Illuminate\Support\Str;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
});

function alertAdmin(): User
{
    $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
    $admin = User::factory()->create(['role_id' => $role->id]);
    $admin->roles()->sync([$role->id]);

    return $admin->fresh(['roles.permissions']);
}

function managedCity(bool $active = true): City
{
    return City::query()->create([
        'name' => 'City '.Str::random(6),
        'code' => Str::upper(Str::random(3)),
        'region' => 'Region',
        'is_active' => $active,
    ]);
}

function alertPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Scheduled maintenance',
        'message' => '<p>The platform will be down on <strong>Sunday</strong>.</p>',
        'type' => 'warning',
        'display_format' => 'banner',
        'is_dismissible' => true,
        'target_roles' => ['all'],
        'target_cities' => ['all'],
        'target_user_ids' => [],
        'end_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'is_active' => true,
    ], $overrides);
}

it('lets an administrator manage announcements', function () {
    $admin = alertAdmin();

    $this->actingAs($admin)
        ->get(route('alerts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('alerts/index')
            ->where('can.create', true)
        );

    $this->actingAs($admin)->get(route('alerts.create'))->assertOk();
});

it('keeps announcements away from users without the permission', function () {
    $role = Role::query()->where('name', Role::SELLER)->firstOrFail();
    $seller = User::factory()->create(['role_id' => $role->id]);
    $seller->roles()->sync([$role->id]);

    $this->actingAs($seller->fresh(['roles.permissions']))
        ->get(route('alerts.index'))
        ->assertForbidden();
});

it('publishes an announcement and records who wrote it', function () {
    $admin = alertAdmin();

    $this->actingAs($admin)
        ->post(route('alerts.store'), alertPayload())
        ->assertRedirect(route('alerts.index'));

    $alert = Alert::query()->sole();

    expect($alert->title)->toBe('Scheduled maintenance')
        ->and($alert->created_by)->toBe($admin->id)
        ->and($alert->target_roles)->toBe(['all'])
        ->and($alert->status())->toBe('active');
});

it('strips anything dangerous out of the message before storing it', function () {
    $admin = alertAdmin();

    $this->actingAs($admin)
        ->post(route('alerts.store'), alertPayload([
            'message' => '<p onclick="steal()">Hello<script>alert(1)</script></p>'
                .'<p style="position:fixed;top:0;color:red">Covered</p>',
        ]))
        ->assertRedirect();

    $message = Alert::query()->sole()->message;

    // The formatting survives, the payload does not — and it is the stored
    // value that is clean, not merely the rendered one.
    expect($message)->toContain('Hello')
        ->and($message)->toContain('color: red')
        ->and($message)->not->toContain('script')
        ->and($message)->not->toContain('onclick')
        ->and($message)->not->toContain('position');
});

it('refuses an announcement that would reach nobody', function () {
    $this->actingAs(alertAdmin())
        ->post(route('alerts.store'), alertPayload([
            'target_roles' => [],
            'target_cities' => [],
            'target_user_ids' => [],
        ]))
        ->assertSessionHasErrors('target_roles');

    expect(Alert::query()->count())->toBe(0);
});

it('refuses an audience nobody could ever belong to', function () {
    $admin = alertAdmin();
    $inactive = managedCity(active: false);

    // A city id of zero is the shape a broken picker sends: it validates as an
    // integer, matches no one, and leaves an announcement that looks published
    // yet reaches nobody. The same goes for a role that was never offered.
    foreach ([0, 999999, $inactive->id] as $cityId) {
        $this->actingAs($admin)
            ->post(route('alerts.store'), alertPayload(['target_cities' => [$cityId]]))
            ->assertSessionHasErrors('target_cities.0');
    }

    $this->actingAs($admin)
        ->post(route('alerts.store'), alertPayload(['target_roles' => ['Sorcerer']]))
        ->assertSessionHasErrors('target_roles.0');

    expect(Alert::query()->count())->toBe(0);
});

it('accepts a real city alongside the everyone marker', function () {
    $city = managedCity();

    $this->actingAs(alertAdmin())
        ->post(route('alerts.store'), alertPayload([
            'target_roles' => [Role::SELLER],
            'target_cities' => [$city->id],
        ]))
        ->assertRedirect();

    expect(Alert::query()->sole()->target_cities)->toBe([$city->id]);
});

it('accepts an announcement aimed only at named people', function () {
    $admin = alertAdmin();
    $recipient = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('alerts.store'), alertPayload([
            'target_roles' => [],
            'target_cities' => [],
            'target_user_ids' => [$recipient->id],
        ]))
        ->assertRedirect();

    expect(Alert::query()->sole()->target_user_ids)->toBe([$recipient->id]);
});

it('refuses an end date that has already passed', function () {
    $this->actingAs(alertAdmin())
        ->post(route('alerts.store'), alertPayload([
            'end_date' => now()->subHour()->format('Y-m-d\TH:i'),
        ]))
        ->assertSessionHasErrors('end_date');
});

it('toggles an announcement on and off from the table', function () {
    $admin = alertAdmin();
    $alert = Alert::factory()->create();

    $this->actingAs($admin)->patch(route('alerts.toggle', $alert))->assertRedirect();
    expect($alert->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch(route('alerts.toggle', $alert))->assertRedirect();
    expect($alert->fresh()->is_active)->toBeTrue();
});

it('will not put an expired announcement back on air behind the reader’s back', function () {
    $admin = alertAdmin();
    $alert = Alert::factory()->expired()->disabled()->create();

    $this->actingAs($admin)
        ->patch(route('alerts.toggle', $alert))
        ->assertSessionHas('error');

    expect($alert->fresh()->is_active)->toBeFalse();
});

it('hands the edit form a flat announcement it can read straight away', function () {
    $admin = alertAdmin();
    $recipient = User::factory()->create();
    $alert = Alert::factory()->create([
        'target_roles' => [Role::SELLER],
        'target_user_ids' => [$recipient->id],
    ]);

    // Asserting the shape, not just the status: a JsonResource wraps itself in
    // `data` unless it is resolved, and the form reads these keys directly.
    $this->actingAs($admin)
        ->get(route('alerts.edit', $alert))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('alerts/edit')
            ->where('alert.title', $alert->title)
            ->where('alert.target_roles', [Role::SELLER])
            ->where('alert.target_user_ids', [$recipient->id])
            ->has('alert.end_date')
            ->has('alert.display_format')
            ->missing('alert.data')
            ->where('selectedUsers.0.value', $recipient->id)
        );
});

it('edits and deletes an announcement', function () {
    $admin = alertAdmin();
    $alert = Alert::factory()->create();

    $this->actingAs($admin)->get(route('alerts.edit', $alert))->assertOk();

    $this->actingAs($admin)
        ->put(route('alerts.update', $alert), alertPayload(['title' => 'Renamed']))
        ->assertRedirect(route('alerts.index'));

    expect($alert->fresh()->title)->toBe('Renamed');

    $this->actingAs($admin)->delete(route('alerts.destroy', $alert))->assertRedirect();
    expect(Alert::query()->count())->toBe(0);
});

it('hands the alerts on display to every page through the shared props', function () {
    Alert::factory()->create(['title' => 'Heads up']);
    Alert::factory()->modal()->create(['title' => 'Read me']);

    $this->actingAs(alertAdmin())
        ->get(route('alerts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('announcements.banners.0.title', 'Heads up')
            ->where('announcements.modals.0.title', 'Read me')
        );
});

it('hides a dismissed banner for the rest of the session', function () {
    $admin = alertAdmin();
    $alert = Alert::factory()->create(['title' => 'Heads up']);

    $this->actingAs($admin)->post(route('alerts.dismiss', $alert))->assertRedirect();

    $this->actingAs($admin)
        ->get(route('alerts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('announcements.banners', []));

    // The record lives in the session, so signing in again brings it back.
    $this->app['session']->forget(AlertService::SESSION_KEY);

    $this->actingAs($admin)
        ->get(route('alerts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('announcements.banners.0.title', 'Heads up'));
});

it('refuses to dismiss a banner that was pinned on purpose', function () {
    $admin = alertAdmin();
    $pinned = Alert::factory()->permanent()->create();

    $this->actingAs($admin)->post(route('alerts.dismiss', $pinned))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('alerts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('announcements.banners.0.id', $pinned->id));
});

it('refuses to dismiss an alert that was never addressed to the reader', function () {
    $admin = alertAdmin();
    $someoneElse = User::factory()->create();
    $private = Alert::factory()->forUsers([$someoneElse->id])->create();

    $this->actingAs($admin)->post(route('alerts.dismiss', $private))->assertForbidden();
});

it('spells the audience out for the management table', function () {
    $admin = alertAdmin();
    $city = City::query()->create([
        'name' => 'Tanger',
        'code' => 'TNG',
        'region' => 'Nord',
        'is_active' => true,
    ]);

    Alert::factory()->forRoles([Role::SELLER])->forCities([$city->id])->create();

    $this->actingAs($admin)
        ->get(route('alerts.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('alerts.data.0.target_roles', [Role::SELLER])
            ->where('alerts.data.0.target_cities', [$city->id])
            ->where('cities.0.label', 'Tanger')
        );
});
