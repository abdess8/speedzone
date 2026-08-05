<?php

namespace App\Http\Responses;

use App\Enums\UserStatus;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = Auth::user();

        if ($user && ($user->status ?? UserStatus::Active) === UserStatus::PendingApproval) {
            return redirect()->intended(route('account.pending-approval'));
        }

        if ($user && $user->isSeller() && $user->isAccountActive()) {
            return redirect()->intended(route('dashboard.seller'));
        }

        return redirect()->intended(config('fortify.home'));
    }
}
