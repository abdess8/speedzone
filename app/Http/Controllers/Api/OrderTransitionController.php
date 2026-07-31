<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderTransitionService;
use Illuminate\Http\JsonResponse;

class OrderTransitionController extends Controller
{
    public function __construct(private readonly OrderTransitionService $transitionService) {}

    public function __invoke(TransitionOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('updateStatus', $order);

        $validated = $request->validated();

        $order = $this->transitionService->transition(
            $order,
            $validated['to_status'],
            $request->user(),
            $validated['comment'] ?? null,
            $request->transitionContext()
        );

        $order->load(['city', 'seller', 'statusHistories.user']);

        return OrderResource::make($order)->response();
    }
}
