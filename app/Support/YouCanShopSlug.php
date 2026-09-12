<?php

namespace App\Support;

/**
 * Turns a YouCan shop URL or hostname into the slug the API talks about.
 *
 * Sellers paste whatever they see in the browser — `https://atlas.youcan.shop`,
 * `atlas.youcan.store`, or just `atlas`. The connector stores one canonical slug.
 */
final class YouCanShopSlug
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        $value = (string) preg_replace('#^https?://#', '', $value);
        $host = explode('/', $value)[0] ?? $value;
        $host = explode(':', $host)[0] ?? $host;
        $slug = (string) preg_replace('#\.(youcan\.(shop|store)|ycan\.(shop|vip))$#', '', $host);

        $slug = trim($slug, '.');

        return $slug === '' ? null : $slug;
    }
}
