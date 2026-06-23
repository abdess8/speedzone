<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $userLocale = $request->user()?->locale;

        if ($userLocale && in_array($userLocale, self::SUPPORTED, true)) {
            return $userLocale;
        }

        $sessionLocale = $request->session()->get('locale');

        if ($sessionLocale && in_array($sessionLocale, self::SUPPORTED, true)) {
            return $sessionLocale;
        }

        $default = config('app.locale', 'fr');

        return in_array($default, self::SUPPORTED, true) ? $default : 'fr';
    }
}
