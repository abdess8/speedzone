<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReturnController as WebReturnController;
use App\Http\Requests\ChangeReturnStatusRequest;
use App\Http\Requests\ReturnScanRequest;
use App\Http\Requests\StoreReturnRequest;
use App\Http\Requests\UpdateReturnCustomerDataRequest;
use App\Http\Resources\OrderReturnResource;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\ReturnQueryService;
use App\Services\ReturnScanService;
use App\Services\ReturnService;
use App\Services\ReturnTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReturnController extends Controller
{
    public function __construct(
        private readonly ReturnService $returns,
        private readonly ReturnQueryService $returnQuery,
        private readonly ReturnTransitionService $transitions,
        private readonly ReturnScanService $scanService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OrderReturn::class);

        $returns = $this->returnQuery->build($request, $request->user())
            ->paginate($this->returnQuery->perPage($request))
            ->withQueryString();

        return OrderReturnResource::collection($returns);
    }

    public function store(StoreReturnRequest $request): JsonResponse
    {
        $order = Order::query()->findOrFail($request->integer('order_id'));
        $role = WebReturnController::resolveInitiatorRoleForUser(
            $request->user(),
            $request->input('initiated_by_role')
        );

        $return = $this->returns->create(
            $order,
            $request->user(),
            $role,
            $request->string('reason')->toString(),
            $request->input('return_notes'),
            $request->input('current_location_city_id'),
        );

        return response()->json([
            'data' => OrderReturnResource::make($return)->resolve($request),
        ], 201);
    }

    public function show(Request $request, OrderReturn $return): OrderReturnResource
    {
        $this->authorize('view', $return);

        $return->load([
            'order.seller.city',
            'order.city',
            'order.sector',
            'creator.roles',
            'currentLocationCity',
            'updatedCity',
            'statusHistories.changedBy.roles',
        ]);

        return OrderReturnResource::make($return);
    }

    public function changeStatus(ChangeReturnStatusRequest $request, OrderReturn $return): JsonResponse
    {
        $return = $this->transitions->transition(
            $return,
            $request->string('status')->toString(),
            $request->user(),
            $request->input('comment'),
            $request->input('current_location_city_id'),
        );

        return response()->json([
            'data' => OrderReturnResource::make($return)->resolve($request),
        ]);
    }

    public function updateCustomerData(UpdateReturnCustomerDataRequest $request, OrderReturn $return): JsonResponse
    {
        $return = $this->returns->updateCustomerData($return, $request->user(), $request->validated());

        return response()->json([
            'data' => OrderReturnResource::make($return)->resolve($request),
        ]);
    }

    public function moveToDepot(Request $request, OrderReturn $return): JsonResponse
    {
        $this->authorize('updateStatus', $return);

        $return = $this->transitions->moveToDepot(
            $return,
            $request->user(),
            $request->input('comment'),
            $request->input('current_location_city_id'),
        );

        return response()->json([
            'data' => OrderReturnResource::make($return)->resolve($request),
        ]);
    }

    public function scan(ReturnScanRequest $request): JsonResponse
    {
        return response()->json(
            $this->scanService->validateScan(
                $request->user(),
                $request->string('scan')->toString()
            )
        );
    }

    public function processScan(ReturnScanRequest $request): JsonResponse
    {
        $return = $this->scanService->processScan(
            $request->user(),
            $request->string('scan')->toString(),
            $request->input('comment'),
        );

        return response()->json([
            'success' => true,
            'data' => OrderReturnResource::make($return->load([
                'order.city',
                'currentLocationCity',
                'statusHistories.changedBy',
            ]))->resolve($request),
        ]);
    }
}
