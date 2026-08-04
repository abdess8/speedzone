<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    /**
     * Public OWL Delivery marketing landing page.
     *
     * Rendered for both guests and authenticated users. The page itself is
     * completely independent from the dashboard layout.
     */
    public function index(): Response
    {
        return Inertia::render('landing/Home', [
            'authenticated' => auth()->check(),
        ]);
    }

    /**
     * Public parcel tracking result.
     *
     * Looks up an order by its tracking number without requiring auth and
     * exposes only the information safe to show publicly (status timeline,
     * destination city and current status). When nothing matches we still
     * render the page with `found = false` so the SPA can display
     * "Numéro introuvable.".
     */
    public function track(Request $request, string $trackingNumber): Response
    {
        $trackingNumber = trim($trackingNumber);

        $order = Order::query()
            ->where('tracking_number', $trackingNumber)
            ->with(['city', 'statusHistories'])
            ->first();

        if ($order === null) {
            return Inertia::render('landing/Tracking', [
                'trackingNumber' => $trackingNumber,
                'found' => false,
                'order' => null,
            ]);
        }

        $timeline = $order->statusHistories->map(fn ($history) => [
            'status' => $history->status->value,
            'label' => $history->status->label(),
            'color' => $history->status->color(),
            'icon' => $history->status->icon(),
            'is_system' => (bool) $history->is_system,
            'date' => optional($history->created_at)->toIso8601String(),
        ])->values();

        return Inertia::render('landing/Tracking', [
            'trackingNumber' => $order->tracking_number,
            'found' => true,
            'order' => [
                'tracking_number' => $order->tracking_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'status_color' => $order->status->color(),
                'status_icon' => $order->status->icon(),
                'city' => optional($order->city)->name,
                'timeline' => $timeline,
            ],
        ]);
    }
}
