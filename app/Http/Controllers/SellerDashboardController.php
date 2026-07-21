<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SellerDashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        abort_unless($user && $user->isSeller() && $user->isAccountActive(), 403);

        return Inertia::render('dashboards/ecommerce/index');
    }
}
