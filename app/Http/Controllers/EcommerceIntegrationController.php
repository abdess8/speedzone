<?php

namespace App\Http\Controllers;

use App\Support\EcommerceIntegrationPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalogue of the storefronts a vendor can plug into the platform.
 *
 * Read-only for now: the screen states which connectors exist and which are
 * still being built. The connection flow itself — OAuth handshake, webhook
 * registration, product and order mapping — lands per platform.
 */
class EcommerceIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('integrations/index', [
            // The platform list itself lives client-side; only the rights the
            // screen has to honour travel with the response.
            'can' => [
                'manage' => $user->hasPermission(EcommerceIntegrationPermissions::MANAGE),
            ],
            'selected' => $request->string('platform')->toString() ?: null,
        ]);
    }
}
