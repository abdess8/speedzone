<?php

namespace App\Http\Controllers;

use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Http\Requests\AssignReturnDriverRequest;
use App\Http\Requests\ChangeReturnStatusRequest;
use App\Http\Requests\ReturnHandBackRequest;
use App\Http\Requests\ReturnScanRequest;
use App\Http\Requests\StoreReturnRequest;
use App\Http\Requests\UpdateReturnCustomerDataRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderReturnResource;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use App\Services\ReturnHandBackService;
use App\Services\ReturnQrCodeService;
use App\Services\ReturnQueryService;
use App\Services\ReturnScanService;
use App\Services\ReturnService;
use App\Services\ReturnTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReturnController extends Controller
{
    public function __construct(
        private readonly ReturnService $returns,
        private readonly ReturnQueryService $returnQuery,
        private readonly ReturnTransitionService $transitions,
        private readonly ReturnScanService $scanService,
        private readonly ReturnQrCodeService $qrCodes,
        private readonly ReturnHandBackService $handBacks,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OrderReturn::class);

        $user = $request->user();
        $returns = $this->returnQuery->build($request, $user)
            ->paginate($this->returnQuery->perPage($request))
            ->withQueryString();

        return Inertia::render('returns/index', [
            'returns' => OrderReturnResource::collection($returns)->response()->getData(true),
            'stats' => $this->returnQuery->statusCounts($request, $user),
            'filters' => $request->only([
                'search', 'status', 'city_id', 'seller_id', 'reason', 'created_from', 'created_to', 'per_page',
            ]),
            'filterOptions' => [
                'statuses' => ReturnStatus::options(),
                'reasons' => ReturnReason::options(),
                'cities' => City::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
                'sellers' => $user->hasPermission('returns.read.all')
                    ? $this->returns->sellerOptions()->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => $u->full_name,
                    ])->all()
                    : [],
                'pageSizes' => [10, 15, 25, 50],
            ],
            'can' => $this->abilities($request),
        ]);
    }

    /**
     * The bulk restitution screen: scan the shelf, name the drivers, send out.
     */
    public function handBack(Request $request): Response
    {
        $this->authorize('viewAny', OrderReturn::class);

        if (! $this->returns->canAssignDrivers($request->user())) {
            abort(403, __('returns.errors.assign_forbidden'));
        }

        $cityId = $request->integer('city_id') ?: $request->user()->city_id;

        return Inertia::render('returns/hand-back', [
            'pending' => $this->handBacks->pending($cityId ?: null),
            'drivers' => $this->driverPayload($cityId ?: null),
            'cities' => City::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => ['city_id' => $cityId ?: ''],
        ]);
    }

    public function handBackScan(ReturnScanRequest $request): JsonResponse
    {
        return response()->json(
            $this->handBacks->validateScan($request->user(), $request->string('scan')->toString())
        );
    }

    public function handBackDispatch(ReturnHandBackRequest $request): RedirectResponse
    {
        $result = $this->handBacks->dispatchBatch(
            $request->user(),
            $request->input('items', []),
            $request->input('comment'),
        );

        $message = trans_choice('returns.hand_back.dispatched', $result['dispatched'], ['count' => $result['dispatched']]);

        return back()
            ->with($result['failures'] === [] ? 'success' : 'warning', $message)
            ->with('handBackFailures', $result['failures']);
    }

    public function assignDriver(AssignReturnDriverRequest $request, OrderReturn $return): RedirectResponse
    {
        $driver = User::query()->findOrFail($request->integer('driver_id'));

        if ($request->boolean('dispatch')) {
            $this->transitions->handBack($return, $request->user(), $driver, $request->input('comment'));

            return back()->with('success', __('returns.hand_back.dispatched_one'));
        }

        $this->returns->assignDriver($return, $driver, $request->user());

        return back()->with('success', __('returns.hand_back.assigned'));
    }

    public function store(StoreReturnRequest $request): RedirectResponse
    {
        $order = Order::query()->findOrFail($request->integer('order_id'));
        $role = $this->resolveInitiatorRole($request);

        $return = $this->returns->create(
            $order,
            $request->user(),
            $role,
            $request->string('reason')->toString(),
            $request->input('return_notes'),
            $request->input('current_location_city_id'),
        );

        return redirect()
            ->route('returns.show', $return)
            ->with('success', "Return {$return->reference} created successfully.");
    }

    public function show(Request $request, OrderReturn $return): Response
    {
        $this->authorize('view', $return);

        $return->load([
            'order.seller.roles',
            'order.seller.city',
            'order.city',
            'order.sector',
            'creator.roles',
            'assignedDriver.roles',
            'currentLocationCity',
            'updatedCity',
            'statusHistories.changedBy.roles',
        ]);

        $canAssign = $request->user()->can('assignDriver', $return)
            && in_array($return->status, [ReturnStatus::ARRIVED_VENDOR_HUB, ReturnStatus::IN_DELIVERY_TO_VENDOR], true);

        return Inertia::render('returns/show', [
            'orderReturn' => OrderReturnResource::make($return)->resolve($request),
            'allowedTransitions' => $this->transitionOptions($return, $request->user()),
            'qrCode' => $request->user()->can('printQr', $return)
                ? $this->qrCodes->dataUri($return->reference)
                : null,
            'cities' => City::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            // Only the hand-back leg needs a driver, so the list is not built
            // for a parcel still sitting in another city's hub.
            'drivers' => $canAssign ? $this->driverPayload($return->handBackCityId()) : [],
            'can' => array_merge($this->abilities($request), [
                'view' => true,
                'update_status' => $request->user()->can('updateStatus', $return),
                'edit_customer_data' => $request->user()->can('editCustomerData', $return),
                'assign_driver' => $canAssign,
                'scan' => $request->user()->can('scan', $return),
                'print_qr' => $request->user()->can('printQr', $return),
            ]),
        ]);
    }

    public function changeStatus(ChangeReturnStatusRequest $request, OrderReturn $return): RedirectResponse
    {
        $status = $request->string('status')->toString();

        // Going out for restitution and naming the carrier are the same act.
        if ($status === ReturnStatus::IN_DELIVERY_TO_VENDOR->value) {
            $this->transitions->handBack(
                $return,
                $request->user(),
                User::query()->findOrFail($request->integer('driver_id')),
                $request->input('comment'),
            );

            return back()->with('success', __('returns.hand_back.dispatched_one'));
        }

        $this->transitions->transition(
            $return,
            $status,
            $request->user(),
            $request->input('comment'),
            $request->input('current_location_city_id'),
        );

        return back()->with('success', __('returns.status_updated'));
    }

    public function updateCustomerData(UpdateReturnCustomerDataRequest $request, OrderReturn $return): RedirectResponse
    {
        $this->returns->updateCustomerData($return, $request->user(), $request->validated());

        return back()->with('success', 'Return customer information updated successfully.');
    }

    public function receiveAtHub(Request $request, OrderReturn $return): RedirectResponse
    {
        $this->authorize('updateStatus', $return);

        $this->transitions->receiveAtHub(
            $return,
            $request->user(),
            $request->input('comment'),
            $request->input('current_location_city_id'),
        );

        return back()->with('success', 'Return received at the delivery city hub.');
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

    public function processScan(ReturnScanRequest $request): RedirectResponse|JsonResponse
    {
        $return = $this->scanService->processScan(
            $request->user(),
            $request->string('scan')->toString(),
            $request->input('comment'),
            $request->input('driver_id'),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'return' => OrderReturnResource::make($return->load([
                    'order.city',
                    'currentLocationCity',
                    'statusHistories.changedBy',
                ]))->resolve($request),
            ]);
        }

        return redirect()
            ->route('returns.show', $return)
            ->with('success', "Return {$return->reference} processed successfully.");
    }

    public function eligibleOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'seller_id']);

        // A seller only ever sees his own parcels; back-office staff opening a
        // return on his behalf need the whole pool, filtered by seller.
        if ($user->canCreateReturnRequest()) {
            $orders = $this->returns->getEligibleOrders($user, $filters);
        } elseif ($user->hasPermission('returns.manage')) {
            $orders = $this->returns->getEligibleOrdersForAdmin($filters);
        } else {
            abort(403, __('returns.errors.create_forbidden'));
        }

        return response()->json([
            'data' => OrderResource::collection($orders)->resolve($request),
        ]);
    }

    public function qr(OrderReturn $return): HttpResponse
    {
        $this->authorize('printQr', $return);

        return response($this->qrCodes->svg($return->reference, 300), 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }

    /**
     * Public scan landing — resolves return reference from QR code URL.
     */
    public function track(string $reference): Response|RedirectResponse
    {
        $return = OrderReturn::query()
            ->where('reference', strtoupper($reference))
            ->with(['order.city', 'currentLocationCity'])
            ->firstOrFail();

        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $this->authorize('view', $return);

        return redirect()->route('returns.show', $return);
    }

    private function resolveInitiatorRole(StoreReturnRequest $request): ReturnInitiatedByRole
    {
        return self::resolveInitiatorRoleForUser(
            $request->user(),
            $request->input('initiated_by_role')
        );
    }

    /**
     * Who is opening this return, decided by what the user actually is rather
     * than by what the form claims — the two seller-side and driver-side flows
     * carry different eligibility rules, and back-office staff get their own.
     */
    public static function resolveInitiatorRoleForUser(User $user, ?string $requested = null): ReturnInitiatedByRole
    {
        if ($user->canCreateDriverReturn()) {
            return ReturnInitiatedByRole::DRIVER;
        }

        if ($user->canCreateReturnRequest()) {
            return ReturnInitiatedByRole::SELLER;
        }

        return ReturnInitiatedByRole::ADMIN;
    }

    /**
     * @return array<int, array{id: int, name: string, phone: ?string}>
     */
    private function driverPayload(?int $cityId): array
    {
        return $this->returns->driverOptions($cityId)
            ->map(fn (User $driver) => [
                'id' => $driver->id,
                'name' => $driver->full_name,
                'phone' => $driver->phone_number,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function transitionOptions(OrderReturn $return, $user): array
    {
        return collect($this->transitions->allowedNextStatuses($return, $user))
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => ReturnStatus::from($status)->label(),
                'color' => ReturnStatus::from($status)->color(),
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
            'create' => $user->can('create', OrderReturn::class),
            'create_request' => $user->canCreateReturnRequest(),
            'read_all' => $user->hasPermission('returns.read.all'),
            'manage' => $user->hasPermission('returns.manage'),
            'update_status' => $user->hasPermission('returns.update_status'),
            'hand_back' => $this->returns->canAssignDrivers($user),
            'scan' => $user->hasPermission('returns.update_status') || $user->hasPermission('returns.create'),
        ];
    }
}
