<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePartnerDeliveryStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\PartnerDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PartnerDeliveryController extends Controller
{
    public function __construct(
        private readonly PartnerDeliveryService $partnerDeliveries,
    ) {}

    public function updateStatus(UpdatePartnerDeliveryStatusRequest $request, Order $order): JsonResponse|RedirectResponse
    {
        if (! $order->partner_id) {
            abort(422, 'Only partner deliveries can be modified.');
        }

        $this->authorize('partner-delivery.update', $order);

        $updated = $this->partnerDeliveries->updateStatus(
            $order,
            $request->string('status')->toString(),
            $request->user(),
            $request->input('comment'),
        );

        $payload = [
            'success' => true,
            'message' => __('partners.orders.status_updated'),
            'order' => OrderResource::make($updated)->resolve($request),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('success', $payload['message']);
    }
}
