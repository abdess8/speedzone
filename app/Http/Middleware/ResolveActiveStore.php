<?php

namespace App\Http\Middleware;

use App\Support\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the store the request is looking at and arms the isolation scope.
 *
 * Runs on every authenticated request so that a store-bound actor can never
 * reach a controller without a boundary in place.
 */
class ResolveActiveStore
{
    public const SESSION_KEY = 'active_store_id';

    /**
     * Stateless clients (Sanctum tokens, partner integrations) advertise their
     * store here instead of through the session.
     */
    public const HEADER = 'X-Store-Id';

    /**
     * Set once the user picks a shop himself. Distinguishes "defaulted for you"
     * from "chosen", which is what decides whether the login picker appears.
     */
    public const CHOSEN_KEY = 'active_store_chosen';

    /**
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->belongsToStoreAccount()) {
            return $next($request);
        }

        $accessible = $user->accessibleStoreIds();

        // A seller whose account predates the store module (or whose stores were
        // all deactivated) keeps his previous, account-wide visibility rather
        // than being locked out of his own data.
        if ($accessible === []) {
            return $next($request);
        }

        $selected = $this->requestedStoreId($request);

        // The heart of the isolation guarantee: a store id that the user is not
        // a member of is discarded, never honoured. Covers a forged session
        // value as well as access revoked mid-session.
        if ($selected === null || ! in_array($selected, $accessible, true)) {
            $selected = $user->defaultStoreId();

            if ($request->hasSession()) {
                $request->session()->put(self::SESSION_KEY, $selected);
            }
        }

        app(StoreContext::class)->enforceFor($user, $selected);

        return $next($request);
    }

    private function requestedStoreId(Request $request): ?int
    {
        if ($request->hasSession() && $request->session()->has(self::SESSION_KEY)) {
            return (int) $request->session()->get(self::SESSION_KEY);
        }

        $header = $request->header(self::HEADER);

        return $header !== null ? (int) $header : null;
    }
}
