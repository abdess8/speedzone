<?php

namespace App\Http\Middleware;

use App\Support\Domains;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends vitrine traffic to the apex and back-office traffic to the app host.
 *
 * Local/testing leave MARKETING_DOMAIN empty, so this is a no-op there.
 */
class SplitMarketingAndAppHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Domains::splitEnabled()) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $path = '/'.ltrim($request->path(), '/');

        if ($path === '/index.php') {
            $path = '/';
        }

        if (Domains::isExemptFromSplit($path)) {
            return $next($request);
        }

        $status = $request->isMethodSafe() ? 301 : 302;

        if (Domains::isWwwMarketingHost($host)) {
            if (Domains::isMarketingPath($path)) {
                return redirect()->away(Domains::urlOnMarketing($request->getRequestUri()), 301);
            }

            return redirect()->away(Domains::urlOnApp($request->getRequestUri()), 301);
        }

        if (Domains::isMarketingHost($host) && ! Domains::isMarketingPath($path)) {
            return redirect()->away(Domains::urlOnApp($request->getRequestUri()), $status);
        }

        if (Domains::isAppHost($host) && Domains::isMarketingPath($path) && $path !== '/') {
            return redirect()->away(Domains::urlOnMarketing($request->getRequestUri()), $status);
        }

        return $next($request);
    }
}
