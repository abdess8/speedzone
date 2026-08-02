<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level RBAC guard: blocks direct URL access to a module the role has no
 * permission on, so a hidden sidebar entry cannot be bypassed by typing the URL.
 *
 * Several permissions are treated as **any-of**, mirroring the `viewAny` policy
 * methods, and may be passed either as separate middleware arguments or joined
 * by a pipe:
 *
 *     ->middleware('permission:orders.read.all,orders.read.own')
 *     ->middleware('permission:orders.read.all|orders.read.own')
 *
 * This is the coarse role-level gate only. Row-level ownership ("is this order
 * mine / assigned to me?") stays in the policies, because it needs the resolved
 * model instance.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->hasAny($user, $this->normalize($permissions))) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to perform this action.');
        }

        return $next($request);
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function normalize(array $permissions): array
    {
        $normalized = [];

        foreach ($permissions as $permission) {
            foreach (explode('|', $permission) as $candidate) {
                $candidate = trim($candidate);

                if ($candidate !== '') {
                    $normalized[] = $candidate;
                }
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function hasAny(User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
