<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveActiveStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Switches the shop the session is looking at.
 *
 * Backs both the navbar switcher and the store picker shown at login.
 */
class ActiveStoreController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $storeId = (int) $validated['store_id'];

        // Membership is re-checked here rather than trusted from the payload:
        // this endpoint is the only way to write the session key the isolation
        // scope reads from.
        if (! $user->canAccessStore($storeId)) {
            throw ValidationException::withMessages([
                'store_id' => __('stores.errors.not_accessible'),
            ]);
        }

        $request->session()->put(ResolveActiveStore::SESSION_KEY, $storeId);
        $request->session()->put(ResolveActiveStore::CHOSEN_KEY, true);

        return back();
    }
}
