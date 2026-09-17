<?php

namespace App\Support;

/**
 * Shopify `*.myshopify.com` hostname a seller types into the connector.
 *
 * Accepts the bare slug (`atlas`), the hostname, or a full admin URL, and
 * always stores the canonical `atlas.myshopify.com` form used by the REST
 * Admin API.
 */
final class ShopifyShopDomain
{
    public static function normalize(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = explode('/', $value)[0] ?? $value;
        $value = explode('?', $value)[0] ?? $value;
        $value = rtrim($value, '.');

        if ($value === '') {
            return null;
        }

        if (str_ends_with($value, '.myshopify.com')) {
            $slug = substr($value, 0, -strlen('.myshopify.com'));
        } else {
            $slug = $value;
        }

        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug) !== 1) {
            return null;
        }

        return $slug.'.myshopify.com';
    }
}
