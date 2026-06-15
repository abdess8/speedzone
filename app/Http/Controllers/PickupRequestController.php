<?php

namespace App\Http\Controllers;

use App\Enums\PickupRequestStatus;
use App\Http\Requests\AssignPickupDriverRequest;
use App\Http\Requests\BulkScanPickupRequest;
use App\Http\Requests\ChangePickupStatusRequest;
use App\Http\Requests\PickupBulkStatusUpdateRequest;
use App\Http\Requests\PickupScanRequest;
use App\Http\Requests\StorePickupRequestRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PickupRequestResource;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\User;
use App\Services\PickupDeliveryNotePdfService;
use App\Services\PickupRequestQueryService;
use App\Services\PickupRequestService;
use App\Services\PickupRequestTransitionService;
use App\Services\PickupScanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PickupRequestController extends Controller
{
    public function __construct(
        private readonly PickupRequestService $pickups,
        private readonly PickupRequestQueryService $pickupQuery,
        private readonly PickupRequestTransitionService $transitions,
        private readonly PickupScanService $scanService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PickupRequest::class);

        $user = $request->user();
        $pickups = $this->pickupQuery->build($request, $user)
            ->paginate($this->pickupQuery->perPage($request))
            ->withQueryString();

        $pageTitle = $user->hasPermission('pickup_requests.read.assigned') && ! $user->hasPermission('pickup_requests.read.all')
            ? 'My Pickups'
            : 'Pickup Requests';

        return Inertia::render('pickup-requests/index', [
            'pickups' => PickupRequestResource::collection($pickups)->response()->getData(true),
            'filters' => $request->only(['search', 'status', 'seller_id', 'created_from', 'created_to', 'per_page']),
            'filterOptions' => [
                'statuses' => PickupRequestStatus::options(),
                'sellers' => $this->sellerOptions($user),
                'pageSizes' => [10, 15, 25, 50],
            ],
            'eligibleOrders' => $this->eligibleOrders($user),
            'pickupAddresses' => $this->pickupAddresses($user),
            'drivers' => $user->hasPermission('pickup_requests.assign')
                ? $this->pickups->driverOptions()->map(fn (User $driver) => [
                    'id' => $driver->id,
                    'name' => $driver->full_name,
                    'phone' => $driver->phone_number,
                ])->all()
                : [],
            'pageTitle' => $pageTitle,
            'can' => $this->abilities($request),
        ]);
    }

    public function store(StorePickupRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', PickupRequest::class);

        $pickup = $this->pickups->create(
            $request->user(),
            $request->input('order_ids', []),
            $request->string('pickup_address')->toString(),
            $request->input('notes')
        );

        return redirect()
            ->route('pickup-requests.show', $pickup)
            ->with('success', "Pickup request {$pickup->reference} created successfully.");
    }

    public function show(Request $request, PickupRequest $pickupRequest): Response
    {
        $this->authorize('view', $pickupRequest);

        $pickupRequest->load([
            'creator.roles',
            'assignee.roles',
            'orders.city',
            'orders.sector',
            'orders.seller.roles',
            'statusHistories.changedBy.roles',
        ]);

        return Inertia::render('pickup-requests/show', [
            'pickup' => PickupRequestResource::make($pickupRequest)->resolve($request),
            'allowedTransitions' => $this->transitionOptions($pickupRequest, $request->user()),
            'drivers' => $request->user()->hasPermission('pickup_requests.assign')
                ? $this->pickups->driverOptions()->map(fn (User $driver) => [
                    'id' => $driver->id,
                    'name' => $driver->full_name,
                    'phone' => $driver->phone_number,
                ])->all()
                : [],
            'can' => array_merge($this->abilities($request), [
                'view' => true,
                'assign' => $request->user()->can('assign', $pickupRequest),
                'change_status' => $request->user()->can('changeStatus', $pickupRequest),
                'print' => $request->user()->can('print', $pickupRequest),
            ]),
        ]);
    }

    public function assignDriver(AssignPickupDriverRequest $request, PickupRequest $pickupRequest): RedirectResponse
    {
        $this->authorize('assign', $pickupRequest);

        $driver = User::query()->findOrFail($request->integer('driver_id'));

        $this->pickups->assignDriver($pickupRequest, $driver, $request->user());

        return back()->with('success', 'Driver assigned successfully.');
    }

    public function changeStatus(ChangePickupStatusRequest $request, PickupRequest $pickupRequest): RedirectResponse
    {
        $this->authorize('changeStatus', $pickupRequest);

        $this->transitions->transition(
            $pickupRequest,
            $request->string('status')->toString(),
            $request->user(),
            $request->input('comment')
        );

        return back()->with('success', 'Pickup status updated successfully.');
    }

    public function bulkScan(BulkScanPickupRequest $request): RedirectResponse
    {
        $this->authorize('scan', PickupRequest::class);

        $targetStatus = $this->scanService->targetPickupStatus($request->user());

        $result = $this->scanService->bulkStatusUpdate(
            $request->user(),
            $request->input('tracking_numbers', []),
            $targetStatus->value
        );

        if ($result['updated'] === 0) {
            return back()->with('error', 'No orders were updated. Check that scanned orders are valid for your role.');
        }

        return back()->with(
            'success',
            "Updated {$result['updated']} order(s) via bulk scan."
        );
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

    public function bulkStatusUpdate(PickupBulkStatusUpdateRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('scan', PickupRequest::class);

        $result = $this->scanService->bulkStatusUpdate(
            $request->user(),
            $request->input('orders', []),
            $request->string('status')->toString()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'updated' => $result['updated'],
                'orders' => $result['orders']->pluck('tracking_number'),
            ]);
        }

        if ($result['updated'] === 0) {
            return back()->with('error', 'No orders were updated.');
        }

        return back()->with(
            'success',
            "Updated {$result['updated']} order(s) successfully."
        );
    }

    public function pdf(Request $request, PickupRequest $pickupRequest, PickupDeliveryNotePdfService $pdfService): HttpResponse
    {
        $this->authorize('print', $pickupRequest);

        $pdf = $pdfService->build($pickupRequest);
        $fileName = $pdfService->fileName($pickupRequest);

        return $request->boolean('download')
            ? $pdf->download($fileName)
            : $pdf->stream($fileName);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eligibleOrders(User $user): array
    {
        if (! $user->hasPermission('pickup_requests.create')) {
            return [];
        }

        return Order::query()
            ->eligibleForPickup($user->id)
            ->with(['city', 'sector'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Order $order) => OrderResource::make($order)->resolve(request()))
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function pickupAddresses(User $user): array
    {
        $addresses = [];

        if ($user->pickup_address_1) {
            $addresses[] = ['key' => '1', 'label' => 'Pickup Address 1', 'value' => $user->pickup_address_1];
        }

        if ($user->pickup_address_2) {
            $addresses[] = ['key' => '2', 'label' => 'Pickup Address 2', 'value' => $user->pickup_address_2];
        }

        return $addresses;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function sellerOptions(User $user): array
    {
        if (! $user->hasPermission('pickup_requests.read.all')) {
            return [];
        }

        return User::query()
            ->whereHas('pickupRequestsCreated')
            ->orderBy('first_name')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name'])
            ->map(fn (User $seller) => ['id' => $seller->id, 'name' => $seller->full_name])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function transitionOptions(PickupRequest $pickup, User $user): array
    {
        return collect($this->transitions->allowedNextStatuses($pickup, $user))
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => PickupRequestStatus::from($status)->label(),
                'color' => PickupRequestStatus::from($status)->color(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();
        $canScan = $user->can('scan', PickupRequest::class);
        $scanTargetStatus = null;
        $scanMode = null;

        if ($canScan) {
            try {
                $scanMode = $this->scanService->resolveScannerMode($user);
                $scanTargetStatus = $this->scanService->targetPickupStatus($user)->value;
            } catch (\Illuminate\Auth\Access\AuthorizationException) {
                $canScan = false;
            }
        }

        return [
            'create' => $user->can('create', PickupRequest::class),
            'read_all' => $user->hasPermission('pickup_requests.read.all'),
            'assign' => $user->hasPermission('pickup_requests.assign'),
            'change_status' => $user->hasPermission('pickup_requests.change_status'),
            'pickup' => $user->hasPermission('pickup_requests.pickup'),
            'scan' => $canScan,
            'scan_mode' => $scanMode,
            'scan_target_status' => $scanTargetStatus,
        ];
    }
}
