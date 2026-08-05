<?php

namespace App\Listeners;

use App\Enums\UserStatus;
use Illuminate\Auth\Events\Verified;

class UpdateUserStatusOnEmailVerified
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if ($user->status !== UserStatus::PendingEmailVerification) {
            return;
        }

        $user->forceFill([
            'status' => UserStatus::PendingApproval,
        ])->save();
    }
}
