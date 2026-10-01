<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drops host-only copies of the session and CSRF cookies.
 *
 * After SESSION_DOMAIN is set to `.speedzoneexpress.ma`, browsers still send
 * the previous host-only `XSRF-TOKEN` / `speed_zone_session`. PHP then reads
 * the stale token and login returns 419. Incognito has no leftover cookies,
 * which is why it works there.
 */
final class StaleAuthCookies
{
    /**
     * @var array<int, string>
     */
    private const PREVIOUS_SESSION_COOKIES = [
        'speed_zone_session',
    ];

    public static function expireOn(Response $response): Response
    {
        $domain = config('session.domain');

        if (! is_string($domain) || $domain === '') {
            return $response;
        }

        $path = (string) config('session.path', '/');
        $secure = (bool) config('session.secure', true);
        $sameSite = config('session.same_site') ?: 'lax';

        $names = array_filter(array_unique([
            ...self::PREVIOUS_SESSION_COOKIES,
            (string) config('session.cookie'),
            'XSRF-TOKEN',
        ]));

        foreach ($names as $name) {
            $httpOnly = $name !== 'XSRF-TOKEN';

            // Host-only (no Domain attribute) — this is the leftover from
            // before SESSION_DOMAIN was set.
            $response->headers->setCookie(new Cookie(
                $name,
                '',
                1,
                $path,
                null,
                $secure,
                $httpOnly,
                false,
                $sameSite,
            ));
        }

        return $response;
    }
}
