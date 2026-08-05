<?php

namespace App\Support;

use App\Models\User;

/**
 * Canonical permission names for the dashboard, one per family of widgets.
 *
 * The dashboard is the only screen in the application that shows every part of
 * the business at once: what is owed, what is late, who sells the most and how
 * long each driver takes. A warehouse employee needs the operational half of
 * that to do his job and has no business reading the turnover of the shop he
 * packs for — so the panels are grouped by the question they answer and each
 * group is a grant an account owner can withhold.
 *
 * Every name here is inside {@see RolePermissionMatrix::sellerCeiling()}: they
 * are read-only, and deciding who in his own team sees his revenue is exactly
 * the kind of call a vendor makes for himself.
 */
final class DashboardPermissions
{
    /** Opens the screen at all. Without it the API answers 403. */
    public const VIEW = 'dashboard.view';

    /** Cash to collect, COD collected, revenue, average order value. */
    public const VIEW_FINANCIALS = 'dashboard.view_financials';

    /** Where the parcels sit: status breakdown, cities, pending work. */
    public const VIEW_OPERATIONS = 'dashboard.view_operations';

    /** Success rate, delivery times, per-driver ranking. */
    public const VIEW_PERFORMANCE = 'dashboard.view_performance';

    /** Top customers and the new-customer count. */
    public const VIEW_CUSTOMERS = 'dashboard.view_customers';

    /** Who is selling and who is driving: per-seller volume, active headcount. */
    public const VIEW_NETWORK = 'dashboard.view_network';

    /**
     * Section key => permission that reveals it.
     *
     * The keys are what the payload and the Vue components speak; the values are
     * what the RBAC layer speaks. Keeping the mapping here means a renamed widget
     * never silently loses its guard.
     *
     * @var array<string, string>
     */
    public const SECTIONS = [
        'financials' => self::VIEW_FINANCIALS,
        'operations' => self::VIEW_OPERATIONS,
        'performance' => self::VIEW_PERFORMANCE,
        'customers' => self::VIEW_CUSTOMERS,
        'network' => self::VIEW_NETWORK,
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::VIEW, ...array_values(self::SECTIONS)];
    }

    /**
     * What a vendor and his staff see by default: everything about their own shop.
     *
     * @return array<int, string>
     */
    public static function sellerDefaults(): array
    {
        return self::all();
    }

    /**
     * Back-office staff read the operational and performance halves plus the
     * network view, and the money figures they already settle invoices against.
     *
     * @return array<int, string>
     */
    public static function staffDefaults(): array
    {
        return self::all();
    }

    /**
     * A driver's dashboard is his round: what is waiting, what he delivered and
     * how he is doing. Not the turnover of the sellers he collects from.
     *
     * @return array<int, string>
     */
    public static function driverDefaults(): array
    {
        return [self::VIEW, self::VIEW_OPERATIONS, self::VIEW_PERFORMANCE];
    }

    /**
     * Sections this actor may read, keyed by section name.
     *
     * Returned as a map rather than a list because both the service (deciding
     * which aggregates to run) and the client (deciding which panels to mount)
     * ask the same question about one section at a time.
     *
     * @return array<string, bool>
     */
    public static function sectionsFor(User $user): array
    {
        $sections = [];

        foreach (self::SECTIONS as $section => $permission) {
            $sections[$section] = $user->hasPermission($permission);
        }

        return $sections;
    }
}
