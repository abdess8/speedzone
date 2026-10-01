<?php

namespace App\Support;

final class Domains
{
    public static function splitEnabled(): bool
    {
        $app = self::appHost();
        $marketing = self::marketingHost();

        return $app !== null && $marketing !== null && $app !== $marketing;
    }

    public static function appHost(): ?string
    {
        return self::stringOrNull(config('domains.app_host'));
    }

    public static function marketingHost(): ?string
    {
        return self::stringOrNull(config('domains.marketing_host'));
    }

    public static function appUrl(): string
    {
        return rtrim((string) config('domains.app_url', config('app.url')), '/');
    }

    public static function marketingUrl(): string
    {
        $url = rtrim((string) config('domains.marketing_url'), '/');

        return $url !== '' ? $url : self::appUrl();
    }

    public static function isAppHost(string $host): bool
    {
        $app = self::appHost();

        return $app !== null && strtolower($host) === $app;
    }

    public static function isMarketingHost(string $host): bool
    {
        $marketing = self::marketingHost();
        $host = strtolower($host);

        return $marketing !== null && ($host === $marketing || $host === 'www.'.$marketing);
    }

    public static function isWwwMarketingHost(string $host): bool
    {
        $marketing = self::marketingHost();

        return $marketing !== null && strtolower($host) === 'www.'.$marketing;
    }

    /**
     * Paths that belong on the public marketing site when the host split is on.
     *
     * `/` is marketing-only on the apex; the app host uses `/` as the
     * post-login home and is handled separately by the middleware.
     */
    public static function isMarketingPath(string $path): bool
    {
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            return true;
        }

        if ($path === '/tracking' || str_starts_with($path, '/tracking/')) {
            return true;
        }

        return str_starts_with($path, '/storage/');
    }

    public static function isExemptFromSplit(string $path): bool
    {
        $path = '/'.ltrim($path, '/');

        return $path === '/up'
            || $path === '/locale'
            || $path === '/locale/'
            || str_starts_with($path, '/.well-known/')
            || str_starts_with($path, '/build/');
    }

    public static function urlOnApp(string $requestUri): string
    {
        return self::appUrl().self::normalizeRequestUri($requestUri);
    }

    public static function urlOnMarketing(string $requestUri): string
    {
        return self::marketingUrl().self::normalizeRequestUri($requestUri);
    }

    private static function normalizeRequestUri(string $requestUri): string
    {
        if ($requestUri === '' || $requestUri === '/') {
            return '/';
        }

        return str_starts_with($requestUri, '/') ? $requestUri : '/'.$requestUri;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return $value !== '' ? $value : null;
    }
}
