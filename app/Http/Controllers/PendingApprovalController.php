<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PendingApprovalController extends Controller
{
    public function show(): Response
    {
        $user = auth()->user();

        abort_unless($user && $user->isPendingApproval(), 403);

        return Inertia::render('Account/PendingApproval', [
            'user' => $user->only(['first_name', 'last_name', 'email', 'full_name']),
            'supportEmail' => config('mail.from.address'),
        ]);
    }
}
