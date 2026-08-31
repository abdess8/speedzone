<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountEmailController extends Controller
{
    /**
     * Change the address of an account that has not been let into the platform
     * yet — the only edit those users are allowed to make.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->isAccountActive(), 403);

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        if ($validated['email'] === $user->email) {
            return back();
        }

        // Back to square one: the new address is unproven, so the account
        // re-enters the verification step before it can queue for approval.
        $user->forceFill([
            'email' => $validated['email'],
            'email_verified_at' => null,
            'status' => UserStatus::PendingEmailVerification,
        ])->save();

        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')
            ->with('status', __('seller_registration.pending.change_email.updated'));
    }
}
