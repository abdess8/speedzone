<?php

namespace App\Support;

use App\Http\Middleware\ResolveActiveStore;
use App\Models\User;

/**
 * The store the current request is looking at.
 *
 * Resolved once per request by {@see ResolveActiveStore}
 * and read by the `store` global scope, so no controller has to remember to
 * filter on store_id.
 *
 * Enforcement is opt-in per actor: only accounts that are bound to stores
 * (vendor admins and their team members) get a store id here. Admins,
 * dispatchers and drivers never do, and keep the visibility their
 * `orders.read.all` / `.assigned` permissions already grant them.
 *
 * Bound as a singleton in AppServiceProvider — a fresh instance per resolution
 * would silently disable the scope.
 */
class StoreContext
{
    private ?int $storeId = null;

    private bool $enforced = false;

    private ?User $user = null;

    public function enforceFor(User $user, ?int $storeId): void
    {
        $this->user = $user;
        $this->storeId = $storeId;
        $this->enforced = $storeId !== null;
    }

    /**
     * Run a callback with the store boundary temporarily lifted.
     *
     * For deliberate cross-store work (billing runs, admin exports). Prefer this
     * to Model::withoutGlobalScope() when several models are involved.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutBoundary(callable $callback): mixed
    {
        $wasEnforced = $this->enforced;
        $this->enforced = false;

        try {
            return $callback();
        } finally {
            $this->enforced = $wasEnforced;
        }
    }

    public function id(): ?int
    {
        return $this->storeId;
    }

    public function isEnforced(): bool
    {
        return $this->enforced;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    /**
     * Drop the resolved context (queued jobs and console commands must never
     * inherit a store from whatever ran before them).
     */
    public function reset(): void
    {
        $this->storeId = null;
        $this->enforced = false;
        $this->user = null;
    }
}
