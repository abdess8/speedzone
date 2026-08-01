<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $status = $user->status ?? UserStatus::Active;

        if ($status === UserStatus::Active) {
            return $next($request);
        }

        if ($status === UserStatus::PendingApproval) {
            return redirect()->route('account.pending-approval');
        }

        if ($status === UserStatus::Rejected) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('seller_registration.login.rejected')]);
        }

        if ($status === UserStatus::Suspended) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('team.login.suspended')]);
        }

        if ($status === UserStatus::PendingEmailVerification) {
            return redirect()->route('verification.notice');
        }

        abort(403);
    }
}
