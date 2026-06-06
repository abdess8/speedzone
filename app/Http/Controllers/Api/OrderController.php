<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusHistoryResource;
use App\Models\Order;
use App\Services\OrderLabelPdfService;
use App\Services\OrderQueryService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderQueryService $orderQuery,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $orders = $this->orderQuery->build($request, $request->user())
            ->paginate($this->orderQuery->perPage($request))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orderService->create($request->validated(), $request->user());

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order);

        $order->load(['city', 'seller', 'statusHistories.user']);

        return OrderResource::make($order);
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $this->authorize('update', $order);

        $order = $this->orderService->update($order, $request->validated());

        return OrderResource::make($order);
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);

        $order->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Full status timeline for an order.
     */
    public function tracking(Order $order): AnonymousResourceCollection
    {
        $this->authorize('view', $order);

        $order->load(['statusHistories.user']);

        return OrderStatusHistoryResource::collection($order->statusHistories);
    }

    /**
     * Stream the thermal shipping label as a PDF.
     */
    public function pdf(Order $order, OrderLabelPdfService $pdfService): Response
    {
        $this->authorize('print', $order);

        $pdf = $pdfService->build($order);

        return $pdf->stream($pdfService->fileName($order));
    }

    /**
     * Resolve an order by its tracking number (used by the QR tracking page).
     */
    public function trackByNumber(string $trackingNumber): OrderResource
    {
        $order = Order::query()
            ->where('tracking_number', $trackingNumber)
            ->with(['city', 'seller', 'statusHistories.user'])
            ->firstOrFail();

        $this->authorize('view', $order);

        return OrderResource::make($order);
    }
}
