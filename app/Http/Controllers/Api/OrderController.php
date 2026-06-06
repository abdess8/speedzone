<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::query()
            ->when(
                ! $user->hasPermission('orders.read.all'),
                fn ($query) => $query->where('created_by', $user->id)
            )
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $order = Order::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($order, JsonResponse::HTTP_CREATED);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json($order);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);
        $order->update($request->validated());

        return response()->json($order->refresh());
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);
        $order->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
