<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\PermissionLabels;
use App\Support\TranslationBundle;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

/**
 * The roles screen is where an administrator decides who may do what, so a
 * permission he cannot read is a permission he cannot grant safely. Every
 * catalogue entry must therefore carry a headline and a help text in both
 * locales — the generated fallback is a bug, not an acceptable outcome.
 */
function catalogPermissions(): array
{
    return array_map(
        static fn (array $definition) => new Permission($definition),
        PermissionCatalog::all()
    );
}

/**
 * The status grants are labelled from the status enums rather than written out
 * one by one, so they are expected to be absent from the hand-written lists.
 */
function isTransition(Permission $permission): bool
{
    return in_array($permission->type, ['workflow_transition', 'status_transition'], true);
}

/**
 * Comparing a rendered label against the generated fallback cannot tell a
 * missing translation from an English one that happens to match, so coverage
 * is asserted on the keys themselves.
 */
function catalogStrings(string $locale, string $section): array
{
    app()->setLocale($locale);

    return (array) trans('permission_catalog.'.$section);
}

it('gives every permission a headline a human can read', function (string $locale) {
    $names = catalogStrings($locale, 'names');

    $unnamed = array_values(array_map(
        static fn (Permission $permission) => $permission->name,
        array_filter(
            catalogPermissions(),
            static fn (Permission $permission) => ! isTransition($permission)
                && ! array_key_exists($permission->name, $names)
        )
    ));

    expect($unnamed)->toBe([], "Still showing a raw action name in '{$locale}': ".implode(', ', $unnamed));
})->with(['fr', 'en']);

it('labels every status grant from the status vocabulary', function (string $locale) {
    app()->setLocale($locale);

    $raw = [];

    foreach (catalogPermissions() as $permission) {
        if (! isTransition($permission)) {
            continue;
        }

        // Str::headline of the bare action is what the fallback would emit.
        if (PermissionLabels::permissionLabel($permission) === Str::headline($permission->action)) {
            $raw[] = $permission->name;
        }
    }

    expect($raw)->toBe([], "Status grant left unlabelled in '{$locale}': ".implode(', ', $raw));
})->with(['fr', 'en']);

it('gives every permission a help text', function (string $locale) {
    app()->setLocale($locale);

    $undocumented = [];

    foreach (catalogPermissions() as $permission) {
        if (PermissionLabels::permissionDescription($permission) === null) {
            $undocumented[] = $permission->name;
        }
    }

    expect($undocumented)->toBe([], "No (i) help text in '{$locale}': ".implode(', ', $undocumented));
})->with(['fr', 'en']);

it('names every resource the permissions are filed under', function (string $locale) {
    $headings = catalogStrings($locale, 'resources');

    $unnamed = array_values(array_filter(
        array_unique(array_column(PermissionCatalog::all(), 'resource')),
        static fn (string $resource) => ! array_key_exists($resource, $headings)
    ));

    expect($unnamed)->toBe([], "Untranslated group heading in '{$locale}': ".implode(', ', $unnamed));
})->with(['fr', 'en']);

/**
 * A permission name carries dots, and a dotted translation key is read by
 * Laravel as a path through nested arrays: 'names.orders.read.own' looked for a
 * 'read' array inside an 'orders' array, found nothing, and every scoped
 * permission silently fell back to "Read (Own)".
 */
it('resolves a permission whose name contains dots', function () {
    app()->setLocale('fr');

    $scoped = new Permission([
        'name' => 'orders.read.own',
        'resource' => 'orders',
        'action' => 'read',
        'scope' => 'own',
        'type' => 'resource',
    ]);

    expect(PermissionLabels::permissionLabel($scoped))
        ->not->toBe('Read (Own)')
        ->toContain('commandes');
});

it('speaks of a status change in the words used on the parcel screens', function () {
    app()->setLocale('fr');

    $workflow = new Permission([
        'name' => 'orders.transition.to_in_delivery_city',
        'resource' => 'orders',
        'action' => 'transition',
        'scope' => null,
        'type' => 'workflow_transition',
    ]);

    $pair = new Permission([
        'name' => 'orders.status_transition.out_for_delivery.delivered',
        'resource' => 'orders',
        'action' => 'status_transition',
        'scope' => null,
        'type' => 'status_transition',
    ]);

    expect(PermissionLabels::permissionLabel($workflow))
        ->toBe('Passer au statut « En ville de livraison »')
        ->and(PermissionLabels::permissionLabel($pair))
        ->toBe('En cours de livraison → Livré');
});

it('only pins a scope badge on the three reaches that mean something', function () {
    app()->setLocale('fr');

    expect(PermissionLabels::scopeLabel('own'))->toBe('ses données')
        ->and(PermissionLabels::scopeLabel('all'))->toBe('tout le monde')
        ->and(PermissionLabels::scopeLabel('assigned'))->toBe('ce qui lui est affecté')
        // Notification grants park their topic in the scope column; it is
        // already spelled out in the headline.
        ->and(PermissionLabels::scopeLabel('seller_registered'))->toBeNull()
        ->and(PermissionLabels::scopeLabel(null))->toBeNull();
});

it('hands the roles screen strings instead of raw permission names', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);

    $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
    $admin = User::factory()->create(['role_id' => $role->id, 'locale' => 'fr']);
    $admin->roles()->sync([$role->id]);

    $this->actingAs($admin->fresh(['roles.permissions']))
        ->get(route('roles.edit', $role))
        ->assertInertia(function ($page) {
            $groups = $page->toArray()['props']['permissionGroups'];
            $orders = collect($groups)->firstWhere('resource', 'orders');
            $permission = collect($orders['permissions'])->firstWhere('name', 'orders.export');

            expect($orders['label'])->toBe('Commandes')
                ->and($permission['label'])->toBe('Exporter les commandes en Excel')
                ->and($permission['description'])->toContain('.xlsx')
                ->and($permission['scope_label'])->toBeNull();
        });
});

/**
 * The catalogue is tens of kilobytes and is resolved server side, so shipping
 * it to the browser on every page would be pure weight.
 */
it('keeps the permission wording out of the browser bundle', function () {
    expect(TranslationBundle::GROUPS)->not->toContain('permission_catalog')
        ->and(TranslationBundle::forLocale('fr'))->not->toHaveKey('permission_catalog');
});
