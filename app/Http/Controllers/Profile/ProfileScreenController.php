<?php

namespace App\Http\Controllers\Profile;

use App\Models\City;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController;
use Laravel\Jetstream\Jetstream;

/**
 * Jetstream's profile screen plus what a seller needs to complete his file.
 *
 * Bound over the package controller in JetstreamServiceProvider instead of
 * re-declaring the route, so profile.show keeps pointing at a single URL.
 */
class ProfileScreenController extends UserProfileController
{
    public function show(Request $request)
    {
        $this->validateTwoFactorAuthenticationState($request);

        $user = $request->user();

        return Jetstream::inertia()->render($request, 'Profile/Show', [
            'confirmsTwoFactorAuthentication' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'sessions' => $this->sessions($request)->all(),
            // Only vendors get the extended form, and the city list is only
            // worth a query for them.
            'cities' => $user?->isSeller()
                ? City::query()->active()->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }
}
