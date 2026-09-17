<?php

namespace App\Support;

use App\Http\Responses\LoginResponse;
use App\Models\Role;
use App\Models\User;
use App\Providers\RouteServiceProvider;

/**
 * Post-login landing pages an admin can assign to a role.
 *
 * Keys are stored on `roles.login_redirect` as a short catalogue id, then
 * resolved to a named route. Keys stay free of extra dots so Laravel and
 * vue-i18n can look them up without treating them as nested arrays.
 */
final class LoginRedirect
{
    public const DASHBOARD = 'dashboard';

    /**
     * Catalogue key => named route, or null for the staff home path.
     *
     * Keys stay free of extra dots so Laravel and vue-i18n can look them up
     * without treating them as nested arrays.
     *
     * @var array<string, string|null>
     */
    private const PAGES = [
        self::DASHBOARD => null,
        'seller-dashboard' => 'dashboard.seller',
        'orders' => 'orders.index',
        'preparation' => 'orders.preparation.index',
        'pickups' => 'pickup-requests.index',
        'transfers' => 'transfers.index',
        'returns' => 'returns.index',
        'products' => 'products.index',
        'invoices' => 'invoices.index',
        'driver-earnings' => 'driver-finance.dashboard',
        'integrations' => 'integrations.index',
        'stores' => 'stores.index',
        'users' => 'users.index',
        'partners' => 'partners.index',
        'notifications' => 'notifications.index',
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::PAGES);
    }

    /**
     * Options for the role form select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $key): array => [
                'value' => $key,
                'label' => __('roles.redirect.pages.'.$key),
            ],
            self::keys()
        );
    }

    public static function url(?string $key): ?string
    {
        if (! is_string($key) || $key === '' || ! array_key_exists($key, self::PAGES)) {
            return null;
        }

        $route = self::PAGES[$key];

        if ($route === null) {
            return RouteServiceProvider::HOME;
        }

        return route($route);
    }

    /**
     * Where this account should land after a successful sign-in.
     *
     * Pending-approval still wins in {@see LoginResponse};
     * this only resolves the working destination for an active account.
     */
    public static function forUser(User $user): string
    {
        $url = self::url(self::keyForUser($user));

        if ($url !== null) {
            return $url;
        }

        if ($user->isSeller() && $user->isAccountActive()) {
            return route('dashboard.seller');
        }

        return (string) config('fortify.home');
    }

    public static function keyForUser(User $user): ?string
    {
        $user->loadMissing(['role', 'roles']);

        $roles = collect([$user->role])
            ->concat($user->roles)
            ->filter()
            ->unique(fn (Role $role): int => (int) $role->id);

        foreach ($roles as $role) {
            if (self::url($role->login_redirect) !== null) {
                return $role->login_redirect;
            }
        }

        return null;
    }
}
