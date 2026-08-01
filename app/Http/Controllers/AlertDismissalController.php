<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Closes an alert for the rest of the reader's session.
 *
 * Available to every signed-in user, unlike the rest of the alert routes: this
 * is the recipient dismissing an announcement, not an administrator managing
 * one.
 */
class AlertDismissalController extends Controller
{
    public function __invoke(Request $request, Alert $alert, AlertService $alerts): RedirectResponse
    {
        // Refusing anything the reader was not shown keeps the session from
        // being filled with arbitrary identifiers, and stops a permanent banner
        // from being dismissed by hand-crafting the request.
        $reachable = $alerts->visibleTo($request->user())
            ->first(fn (Alert $candidate) => $candidate->is($alert) && $candidate->canBeDismissed());

        abort_unless($reachable !== null, 403);

        $alerts->dismiss($alert->id);

        return back();
    }
}
