<?php

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\TranslationBundle;
use App\Support\WorkflowDocumentation;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);
});

function helpTestUser(string $roleName): User
{
    $role = Role::query()->where('name', $roleName)->firstOrFail();
    $user = User::factory()->create(['role_id' => $role->id, 'city_id' => null]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

test('the contract is readable by the parties it binds', function (string $roleName) {
    $this->actingAs(helpTestUser($roleName))
        ->get(route('help.partnership'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('help/partnership')
            ->has('sections', 6)
            ->has('sections.0.points')
        );
})->with([Role::SELLER, Role::DRIVER, Role::ADMIN]);

test('the process viewer describes both journeys and every status', function () {
    $this->actingAs(helpTestUser(Role::SELLER))
        ->get(route('help.processes'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('help/processes')
            ->has('flows', 2)
            ->has('matrices.orders', count(OrderStatus::cases()))
            ->has('matrices.returns', count(ReturnStatus::cases()))
            ->has('contentTypes', 3)
            ->has('billing.seller.formula')
            ->has('billing.driver.formula')
        );
});

test('the documented return circuit is the one the workflow enforces', function () {
    $branch = collect(WorkflowDocumentation::all()['flows'])
        ->firstWhere('key', 'failure')['branch'];

    expect(array_column($branch['steps'], 'key'))
        ->toBe(array_map(fn (ReturnStatus $status) => $status->value, ReturnStatus::pipeline()));
});

test('the frontend receives the help strings it renders', function () {
    expect(TranslationBundle::GROUPS)->toContain('help')
        ->and(TranslationBundle::forLocale('fr'))
        ->toHaveKey('help.processes.tabs.flows');
});
