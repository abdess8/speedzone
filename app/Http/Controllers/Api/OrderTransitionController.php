<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderTransitionService;
use Illuminate\Http\JsonResponse;

class OrderTransitionController extends Controller
{
    public function __construct(private readonly OrderTransitionService $transitionService)
    {
    }

    public function __invoke(TransitionOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $order = $this->transitionService->transition(
            $order,
            $request->validated()['to_status'],
            $request->user()
        );

        return response()->json($order);
    }
}
