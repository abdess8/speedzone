<?php

use App\Models\Role;
use App\Models\User;
use App\Models\UserGuideProgress;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * The Help Center and the progress it remembers.
 *
 * The interesting rule is the audience one: a guide walks a reader through
 * screens he has to be allowed to open, so a role that cannot create orders
 * must not even be told the bulk import guide exists.
 */
beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function guideUserWithRole(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

test('a seller is offered the order guides', function () {
    $this->actingAs(guideUserWithRole('Seller'))
        ->get(route('guides.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $page->component('guides/index');

            $keys = collect($page->toArray()['props']['guides'])->pluck('key');

            expect($keys)->toContain('orders-create', 'orders-import', 'pickups-create', 'returns-request');
        });
});

test('a seller is offered the stock guides', function () {
    $this->actingAs(guideUserWithRole('Seller'))
        ->get(route('guides.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $keys = collect($page->toArray()['props']['guides'])->pluck('key');

            expect($keys)->toContain('stock-catalog', 'stock-shipment', 'stock-inventory');
        });
});

// The depot side counts other people's goods; it never adjusts a vendor's own
// catalog, so the inventory walkthrough would end on a screen it cannot save.
test('a dispatcher is not offered the vendor stock guides', function () {
    $this->actingAs(guideUserWithRole('Dispatcher'))
        ->get(route('guides.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            $keys = collect($page->toArray()['props']['guides'])->pluck('key');

            expect($keys)->not->toContain('stock-catalog', 'stock-shipment', 'stock-inventory');
        });
});

test('a driver is not offered a guide he could not follow', function () {
    $this->actingAs(guideUserWithRole('Driver'))
        ->get(route('guides.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('guides', []));
});

test('finishing a guide is remembered', function () {
    $user = guideUserWithRole('Seller');

    $this->actingAs($user)
        ->postJson(route('guides.progress.store', 'orders-import'), [
            'step' => 6,
            'status' => 'completed',
        ])
        ->assertOk()
        ->assertJsonPath('data.completed', true)
        ->assertJsonPath('data.completed_count', 1);

    // A finished guide reopens at the welcome step, not where it ended.
    $this->assertDatabaseHas('user_guide_progress', [
        'user_id' => $user->id,
        'guide_key' => 'orders-import',
        'completed_count' => 1,
        'last_step_index' => 0,
    ]);
});

test('an interrupted guide remembers where it was left', function () {
    $user = guideUserWithRole('Seller');

    $this->actingAs($user)
        ->postJson(route('guides.progress.store', 'orders-import'), [
            'step' => 3,
            'status' => 'in_progress',
        ])
        ->assertOk()
        ->assertJsonPath('data.completed', false)
        ->assertJsonPath('data.last_step_index', 3);

    $this->actingAs($user)
        ->get(route('guides.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('progress.orders-import.last_step_index', 3)
        );
});

test('replaying a guide counts the run without duplicating the row', function () {
    $user = guideUserWithRole('Seller');

    foreach ([1, 2] as $ignored) {
        $this->actingAs($user)->postJson(route('guides.progress.store', 'orders-import'), [
            'step' => 6,
            'status' => 'completed',
        ])->assertOk();
    }

    expect(UserGuideProgress::where('user_id', $user->id)->count())->toBe(1)
        ->and(UserGuideProgress::where('user_id', $user->id)->value('completed_count'))->toBe(2);
});

test('progress cannot be recorded for a guide the reader is not offered', function () {
    $this->actingAs(guideUserWithRole('Driver'))
        ->postJson(route('guides.progress.store', 'orders-import'), [
            'step' => 0,
            'status' => 'started',
        ])
        ->assertForbidden();
});

test('an unknown guide is a 404, not a new row', function () {
    $this->actingAs(guideUserWithRole('Seller'))
        ->postJson(route('guides.progress.store', 'not-a-guide'), [
            'step' => 0,
            'status' => 'started',
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('user_guide_progress', 0);
});

test('resetting a guide forgets it entirely', function () {
    $user = guideUserWithRole('Seller');

    $this->actingAs($user)->postJson(route('guides.progress.store', 'orders-import'), [
        'step' => 6,
        'status' => 'completed',
    ])->assertOk();

    $this->actingAs($user)
        ->deleteJson(route('guides.progress.destroy', 'orders-import'))
        ->assertOk();

    $this->assertDatabaseCount('user_guide_progress', 0);
});
