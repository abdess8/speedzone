<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignPickupDriverRequest;
use App\Http\Requests\BulkScanPickupRequest;
use App\Http\Requests\ChangePickupStatusRequest;
use App\Http\Requests\PickupBulkStatusUpdateRequest;
use App\Http\Requests\PickupScanRequest;
use App\Http\Requests\StorePickupRequestRequest;
use App\Http\Resources\PickupRequestResource;
use App\Models\PickupRequest;
use App\Models\User;
use App\Services\PickupDeliveryNotePdfService;
use App\Services\PickupRequestQueryService;
use App\Services\PickupRequestService;
use App\Services\PickupRequestTransitionService;
use App\Services\PickupScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PickupRequestController extends Controller
{
    public function __construct(
        private readonly PickupRequestService $pickups,
        private readonly PickupRequestQueryService $pickupQuery,
        private readonly PickupRequestTransitionService $transitions,
        private readonly PickupScanService $scanService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PickupRequest::class);

        $pickups = $this->pickupQuery->build($request, $request->user())
            ->paginate($this->pickupQuery->perPage($request))
            ->withQueryString();

        return PickupRequestResource::collection($pickups);
    }

    public function store(StorePickupRequestRequest $request): JsonResponse
    {
        $this->authorize('create', PickupRequest::class);

        $pickup = $this->pickups->create(
            $request->user(),
            $request->input('order_ids', []),
            $request->string('pickup_address')->toString(),
            $request->input('notes')
        );

        return PickupRequestResource::make($pickup)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PickupRequest $pickupRequest): PickupRequestResource
    {
        $this->authorize('view', $pickupRequest);

        $pickupRequest->load([
            'creator',
            'assignee',
            'orders.city',
            'orders.sector',
            'statusHistories.changedBy',
        ]);

        return PickupRequestResource::make($pickupRequest);
    }

    public function update(ChangePickupStatusRequest $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        $this->authorize('changeStatus', $pickupRequest);

        $pickup = $this->transitions->transition(
            $pickupRequest,
            $request->string('status')->toString(),
            $request->user(),
            $request->input('comment')
        );

        return PickupRequestResource::make($pickup);
    }

    public function assignDriver(AssignPickupDriverRequest $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        $this->authorize('assign', $pickupRequest);

        $driver = User::query()->findOrFail($request->integer('driver_id'));
        $pickup = $this->pickups->assignDriver($pickupRequest, $driver, $request->user());

        return PickupRequestResource::make($pickup);
    }

    public function changeStatus(ChangePickupStatusRequest $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        $this->authorize('changeStatus', $pickupRequest);

        $pickup = $this->transitions->transition(
            $pickupRequest,
            $request->string('status')->toString(),
            $request->user(),
            $request->input('comment')
        );

        return PickupRequestResource::make($pickup);
    }

    public function bulkScan(BulkScanPickupRequest $request): JsonResponse
    {
        $this->authorize('scan', PickupRequest::class);

        $targetStatus = $this->scanService->targetPickupStatus($request->user());

        $result = $this->scanService->bulkStatusUpdate(
            $request->user(),
            $request->input('tracking_numbers', []),
            $targetStatus->value
        );

        return response()->json([
            'success' => $result['updated'] > 0,
            'updated' => $result['updated'],
            'orders' => $result['orders']->pluck('tracking_number'),
        ]);
    }

    public function scan(PickupScanRequest $request): JsonResponse
    {
        $this->authorize('scan', PickupRequest::class);

        return response()->json(
            $this->scanService->validateScan(
                $request->user(),
                $request->string('tracking_number')->toString()
            )
        );
    }

    public function bulkStatusUpdate(PickupBulkStatusUpdateRequest $request): JsonResponse
    {
        $this->authorize('scan', PickupRequest::class);

        $result = $this->scanService->bulkStatusUpdate(
            $request->user(),
            $request->input('orders', []),
            $request->string('status')->toString()
        );

        return response()->json([
            'success' => true,
            'updated' => $result['updated'],
            'orders' => $result['orders']->pluck('tracking_number'),
        ]);
    }

    public function pdf(PickupRequest $pickupRequest, PickupDeliveryNotePdfService $pdfService): HttpResponse
    {
        $this->authorize('print', $pickupRequest);

        return $pdfService->build($pickupRequest)->stream($pdfService->fileName($pickupRequest));
    }
}
