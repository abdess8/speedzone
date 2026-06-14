<?php

namespace App\Http\Controllers;

use App\Enums\PickupRequestStatus;
use App\Http\Requests\AssignPickupDriverRequest;
use App\Http\Requests\BulkScanPickupRequest;
use App\Http\Requests\ChangePickupStatusRequest;
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
use Illuminate\Http\RedirectResponse;
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
            'creator',
            'assignee',
            'orders.city',
            'orders.sector',
            'statusHistories.changedBy',
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
        $result = $this->pickups->bulkScanPickup(
            $request->user(),
            $request->input('tracking_numbers', []),
            PickupRequestStatus::PICKED_UP
        );

        return back()->with(
            'success',
            "Updated {$result['updated']} order(s) via bulk scan."
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

        return [
            'create' => $user->can('create', PickupRequest::class),
            'read_all' => $user->hasPermission('pickup_requests.read.all'),
            'assign' => $user->hasPermission('pickup_requests.assign'),
            'change_status' => $user->hasPermission('pickup_requests.change_status'),
            'pickup' => $user->hasPermission('pickup_requests.pickup'),
            'scan' => $user->hasPermission('pickup_requests.pickup') || $user->hasPermission('pickup_requests.change_status'),
        ];
    }
}
