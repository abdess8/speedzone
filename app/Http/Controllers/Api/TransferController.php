<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeTransferStatusRequest;
use App\Http\Requests\EligibleTransferOrdersRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\TransferBulkReceiveRequest;
use App\Http\Requests\TransferScanRequest;
use App\Http\Requests\UpdateTransferRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Services\TransferQueryService;
use App\Services\TransferScanService;
use App\Services\TransferService;
use App\Services\TransferTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly TransferQueryService $transferQuery,
        private readonly TransferTransitionService $transitions,
        private readonly TransferScanService $scanService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Transfer::class);

        $transfers = $this->transferQuery->build($request, $request->user())
            ->paginate($this->transferQuery->perPage($request))
            ->withQueryString();

        return TransferResource::collection($transfers);
    }

    public function eligibleOrders(EligibleTransferOrdersRequest $request): JsonResponse
    {
        $orders = $this->transfers->getEligibleOrders(
            $request->integer('from_city_id'),
            $request->integer('to_city_id'),
            $request->only([
                'status',
                'search',
                'customer',
                'created_from',
                'created_to',
            ])
        );

        return response()->json([
            'data' => OrderResource::collection($orders)->resolve($request),
        ]);
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $this->authorize('create', Transfer::class);

        $transfer = $this->transfers->create(
            $request->user(),
            $request->integer('from_city_id'),
            $request->integer('to_city_id'),
            $request->input('order_ids', []),
            $request->input('notes'),
            $request->input('assigned_to'),
            $request->contentType(),
            $request->input('return_ids', []),
        );

        return TransferResource::make($transfer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Transfer $transfer): TransferResource
    {
        $this->authorize('view', $transfer);

        $transfer->load([
            'fromCity',
            'toCity',
            'creator.roles',
            'assignee.roles',
            'orders.city',
            'orders.sector',
            'orders.seller.roles',
            'orders.seller.city',
            'orders.stockHubCity',
            'statusHistories.changedBy.roles',
        ]);

        return TransferResource::make($transfer);
    }

    public function update(UpdateTransferRequest $request, Transfer $transfer): TransferResource
    {
        $this->authorize('update', $transfer);

        $transfer = $this->transfers->update($transfer, $request->user(), $request->validated());

        return TransferResource::make($transfer);
    }

    public function dispatch(Request $request, Transfer $transfer): TransferResource
    {
        $this->authorize('dispatch', $transfer);

        $transfer = $this->transitions->dispatch($transfer, $request->user(), $request->input('comment'));

        return TransferResource::make($transfer);
    }

    public function receive(Request $request, Transfer $transfer): TransferResource
    {
        $this->authorize('receive', $transfer);

        $transfer = $this->transitions->receive($transfer, $request->user(), $request->input('comment'));

        return TransferResource::make($transfer);
    }

    public function changeStatus(ChangeTransferStatusRequest $request, Transfer $transfer): TransferResource
    {
        $this->authorize('changeStatus', $transfer);

        $transfer = $this->transitions->transition(
            $transfer,
            $request->string('status')->toString(),
            $request->user(),
            $request->input('comment')
        );

        return TransferResource::make($transfer);
    }

    public function scan(TransferScanRequest $request, Transfer $transfer): JsonResponse
    {
        return response()->json(
            $this->scanService->validateOrderScan(
                $request->user(),
                $transfer,
                $request->string('tracking_number')->toString()
            )
        );
    }

    public function bulkReceive(TransferBulkReceiveRequest $request, Transfer $transfer): JsonResponse
    {
        $result = $this->scanService->bulkReceive(
            $request->user(),
            $transfer,
            $request->input('orders', [])
        );

        return response()->json([
            'success' => true,
            'updated' => $result['updated'],
            'transfer_completed' => $result['transfer_completed'],
            'orders' => $result['orders']->pluck('tracking_number'),
        ]);
    }
}
