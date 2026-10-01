<?php

namespace App\Http\Middleware;

use App\Support\StaleAuthCookies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpireStaleAuthCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        return StaleAuthCookies::expireOn($next($request));
    }
}
