<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Assembles the Laravel translation groups that are handed to the Vue i18n
 * layer through Inertia.
 *
 * Building the bundle requires including one PHP file per group (34 of them,
 * ~64 KB of JSON), which is far too expensive to repeat on every request. The
 * result is therefore cached and invalidated by a fingerprint of the language
 * files themselves, so editing a translation still takes effect without a
 * manual cache flush.
 */
final class TranslationBundle
{
    /**
     * Translation groups exposed to the frontend.
     *
     * @var array<int, string>
     */
    public const GROUPS = [
        'sidebar',
        'navbar',
        'roles',
        'common',
        'orders',
        'pickups',
        'transfers',
        'returns',
        'invoices',
        'driver_invoices',
        'driver_finance',
        'driver_invoice_statuses',
        'driver_transaction_types',
        'driver_transaction_statuses',
        'billing_frequencies',
        'seller_payment_methods',
        'users',
        'stores',
        'team',
        'cities',
        'alerts',
        'partners',
        'partner_auth_types',
        'sectors',
        'driver_zones',
        'profile',
        'support_tickets',
        'support_ticket_statuses',
        'support_ticket_categories',
        'support_object_types',
        'permissions',
        'notifications',
        'seller_registration',
        'user_statuses',
        'dashboard',
        'order_statuses',
        'payment_methods',
        'api_docs',
        'chatbot',
        'guides',
        'help',
        'stock',
        'preparation',
        'stock_adjustment_reasons',
        'stock_movement_sources',
        'stock_reception_statuses',
    ];

    /**
     * How long a built bundle stays cached (invalidated early by fingerprint).
     */
    private const BUNDLE_TTL = 86400;

    /**
     * How long the language-file fingerprint is trusted before being recomputed.
     */
    private const FINGERPRINT_TTL = 60;

    /**
     * The full translation bundle for a locale.
     *
     * @return array<string, mixed>
     */
    public static function forLocale(string $locale): array
    {
        $key = "translations.bundle.{$locale}.".self::fingerprint();

        return Cache::remember($key, self::BUNDLE_TTL, fn () => self::build($locale));
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(string $locale): array
    {
        $bundle = [];

        foreach (self::GROUPS as $group) {
            $bundle[$group] = trans($group, [], $locale);
        }

        return $bundle;
    }

    /**
     * Short hash over everything the built bundle depends on.
     *
     * The group list is folded in alongside the files: adding a group touches
     * no language file, so a fingerprint built from mtimes alone would keep
     * serving the previous bundle — with the new group missing from it — until
     * the TTL ran out. That failure is silent and looks like broken
     * translations in the browser.
     */
    private static function fingerprint(): string
    {
        return substr(md5(self::fileStamp().'|'.implode(',', self::GROUPS)), 0, 12);
    }

    /**
     * Short hash over the language files' modification times.
     */
    private static function fileStamp(): string
    {
        return Cache::remember(
            'translations.fingerprint',
            self::FINGERPRINT_TTL,
            function (): string {
                $stamps = [];

                foreach (glob(lang_path('*/*.php')) ?: [] as $file) {
                    $stamps[] = $file.':'.@filemtime($file);
                }

                return substr(md5(implode('|', $stamps)), 0, 12);
            }
        );
    }
}
