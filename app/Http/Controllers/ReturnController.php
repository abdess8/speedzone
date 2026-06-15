<?php

namespace App\Http\Controllers;

use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Http\Requests\ChangeReturnStatusRequest;
use App\Http\Requests\ReturnScanRequest;
use App\Http\Requests\StoreReturnRequest;
use App\Http\Requests\UpdateReturnCustomerDataRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderReturnResource;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
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
            'currentLocationCity',
            'updatedCity',
            'statusHistories.changedBy.roles',
        ]);

        return Inertia::render('returns/show', [
            'orderReturn' => OrderReturnResource::make($return)->resolve($request),
            'allowedTransitions' => $this->transitionOptions($return, $request->user()),
            'qrCode' => $request->user()->can('printQr', $return)
                ? $this->qrCodes->dataUri($return->reference)
                : null,
            'cities' => City::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'can' => array_merge($this->abilities($request), [
                'view' => true,
                'update_status' => $request->user()->can('updateStatus', $return),
                'edit_customer_data' => $request->user()->can('editCustomerData', $return),
                'scan' => $request->user()->can('scan', $return),
                'print_qr' => $request->user()->can('printQr', $return),
            ]),
        ]);
    }

    public function changeStatus(ChangeReturnStatusRequest $request, OrderReturn $return): RedirectResponse
    {
        $this->transitions->transition(
            $return,
            $request->string('status')->toString(),
            $request->user(),
            $request->input('comment'),
            $request->input('current_location_city_id'),
        );

        return back()->with('success', 'Return status updated successfully.');
    }

    public function updateCustomerData(UpdateReturnCustomerDataRequest $request, OrderReturn $return): RedirectResponse
    {
        $this->returns->updateCustomerData($return, $request->user(), $request->validated());

        return back()->with('success', 'Return customer information updated successfully.');
    }

    public function moveToDepot(Request $request, OrderReturn $return): RedirectResponse
    {
        $this->authorize('updateStatus', $return);

        $this->transitions->moveToDepot(
            $return,
            $request->user(),
            $request->input('comment'),
            $request->input('current_location_city_id'),
        );

        return back()->with('success', 'Return marked as in transit to depot.');
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

        if (! $user->canCreateReturnRequest()) {
            abort(403, __('returns.errors.create_forbidden'));
        }

        $filters = $request->only(['search', 'status', 'seller_id']);
        $orders = $this->returns->getEligibleOrders($user, $filters);

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

    public static function resolveInitiatorRoleForUser(User $user, ?string $requested = null): ReturnInitiatedByRole
    {
        if ($user->canCreateDriverReturn()) {
            return ReturnInitiatedByRole::DRIVER;
        }

        return ReturnInitiatedByRole::SELLER;
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
            'create' => $user->canCreateReturnRequest() || $user->canCreateDriverReturn(),
            'create_request' => $user->canCreateReturnRequest(),
            'read_all' => $user->hasPermission('returns.read.all'),
            'manage' => $user->hasPermission('returns.manage'),
            'update_status' => $user->hasPermission('returns.update_status'),
            'scan' => $user->hasPermission('returns.update_status') || $user->hasPermission('returns.create'),
        ];
    }
}
