<?php

namespace App\Support\Guides;

use App\Models\User;

/**
 * The interactive guides the application ships, and who may see them.
 *
 * Deliberately split from the guides themselves: the *steps* live in
 * `resources/js/guides/definitions/*.json`, because a step is a CSS selector
 * and a placement — knowledge only the frontend has. What the server owns is
 * the part the frontend must not be trusted with, the audience.
 *
 * That audience is decided twice, and the difference matters. `permissions` is
 * a floor nobody can lift from the interface: a guide walks through screens the
 * reader has to be allowed to reach, so offering it to someone who would hit a
 * 403 halfway through is never right. On top of that floor sits the editable
 * grid in `GuideAccess`, where an administrator decides which roles are
 * actually invited.
 *
 * The two halves are joined by `key`. A key listed here with no JSON definition
 * simply does not render, and a definition with no entry here is never offered.
 */
final class GuideCatalog
{
    /**
     * @var array<string, array{route: string, icon: string, category: string, permissions: array<int, string>, minutes: int}>
     */
    private const GUIDES = [
        'orders-create' => [
            'route' => 'orders.create',
            'icon' => 'ri-shopping-basket-2-line',
            'category' => 'orders',
            'permissions' => ['orders.create'],
            'minutes' => 3,
        ],
        'orders-import' => [
            // Where the tour has to be standing before its first step resolves.
            'route' => 'orders.import',
            'icon' => 'ri-file-excel-2-line',
            'category' => 'orders',
            // Any-of, matching `EnsurePermission` and the route middleware on
            // `orders.import` itself.
            'permissions' => ['orders.create'],
            'minutes' => 3,
        ],
        'pickups-create' => [
            'route' => 'pickup-requests.index',
            'icon' => 'ri-truck-line',
            'category' => 'pickups',
            'permissions' => ['pickup_requests.create'],
            'minutes' => 3,
        ],
        'returns-request' => [
            'route' => 'returns.index',
            'icon' => 'ri-arrow-go-back-line',
            'category' => 'returns',
            'permissions' => ['returns.create_request'],
            'minutes' => 2,
        ],
        'invoices-read' => [
            'route' => 'invoices.index',
            'icon' => 'ri-bill-line',
            'category' => 'invoices',
            'permissions' => ['invoices.read.own', 'invoices.read.all'],
            'minutes' => 3,
        ],
        'stock-catalog' => [
            'route' => 'products.index',
            'icon' => 'ri-archive-2-line',
            'category' => 'stock',
            // Not `stock.view`: the tour walks the reader through the creation
            // form, and a reader who may only read the catalog would hit a
            // screen with no "Create" button on the third step.
            'permissions' => ['stock.create_product'],
            'minutes' => 4,
        ],
        'stock-shipment' => [
            'route' => 'stock-receptions.index',
            'icon' => 'ri-truck-line',
            'category' => 'stock',
            'permissions' => ['stock.create_inbound'],
            'minutes' => 4,
        ],
        'stock-inventory' => [
            'route' => 'stock.inventory',
            'icon' => 'ri-list-check-2',
            'category' => 'stock',
            'permissions' => ['stock.adjust'],
            'minutes' => 4,
        ],
        'stores-manage' => [
            'route' => 'stores.index',
            'icon' => 'ri-store-2-line',
            'category' => 'stores',
            'permissions' => ['stores.read'],
            'minutes' => 4,
        ],
        'team-member' => [
            'route' => 'team.index',
            'icon' => 'ri-team-line',
            'category' => 'team',
            'permissions' => ['team.read'],
            'minutes' => 4,
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::GUIDES);
    }

    public static function has(string $key): bool
    {
        return isset(self::GUIDES[$key]);
    }

    /**
     * The permissions a guide presupposes (any-of).
     *
     * @return array<int, string>
     */
    public static function permissionsFor(string $key): array
    {
        return self::GUIDES[$key]['permissions'] ?? [];
    }

    /**
     * Every guide, without any audience filtering — for the roles screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $guides = [];

        foreach (self::GUIDES as $key => $guide) {
            $guides[] = [
                'key' => $key,
                'icon' => $guide['icon'],
                'category' => $guide['category'],
                'minutes' => $guide['minutes'],
                'permissions' => $guide['permissions'],
            ];
        }

        return $guides;
    }

    /**
     * The guides this reader may run, in catalog order.
     *
     * Labels are intentionally absent: they are vue-i18n keys derived from the
     * guide key on the client (`guides.catalog.<key>.title`), so a translation
     * change never requires touching PHP.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $isSuperAdmin = $user->isSuperAdmin();
        $visible = [];

        foreach (self::GUIDES as $key => $guide) {
            if (! $isSuperAdmin) {
                if (! self::hasAnyPermission($user, $guide['permissions'])) {
                    continue;
                }

                if (! GuideAccess::allows($user, $key)) {
                    continue;
                }
            }

            $visible[] = [
                'key' => $key,
                'icon' => $guide['icon'],
                'category' => $guide['category'],
                'minutes' => $guide['minutes'],
                'url' => route($guide['route']),
            ];
        }

        return $visible;
    }

    /**
     * @param  array<int, string>  $permissions  any-of; an empty list means everyone
     */
    private static function hasAnyPermission(User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
