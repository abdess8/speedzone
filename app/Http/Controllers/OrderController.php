<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryOutcome;
use App\Enums\OrderFailureReason;
use App\Enums\OrderStatus;
use App\Enums\PartnerOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\TransferStatus;
use App\Http\Requests\AssignOrderDriverRequest;
use App\Http\Requests\StoreDeliveryOutcomeRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderListResource;
use App\Http\Resources\OrderResource;
use App\Models\City;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Services\DeliveryOutcomeService;
use App\Services\OrderDriverAssignmentService;
use App\Services\OrderLabelPdfService;
use App\Services\OrderQueryService;
use App\Services\OrderService;
use App\Services\OrderStockService;
use App\Services\OrderTransitionService;
use App\Services\Partners\PartnerApiException;
use App\Services\Partners\PartnerDeliveryIngestionService;
use App\Services\ReturnService;
use App\Support\StockPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderQueryService $orderQuery,
        private readonly OrderTransitionService $transitionService,
        private readonly OrderDriverAssignmentService $driverAssignment,
        private readonly PartnerDeliveryIngestionService $partnerIngestion,
        private readonly OrderStockService $orderStock,
        private readonly DeliveryOutcomeService $deliveryOutcome,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        // The list only renders the destination city and sector, so the seller
        // relation is neither eager loaded nor serialised here, and the select
        // is limited to the columns the table shows.
        $orders = $this->orderQuery
            ->build($request, $request->user(), ['city:id,name', 'sector:id,name'])
            ->select(OrderListResource::COLUMNS)
            ->paginate($this->orderQuery->perPage($request))
            ->withQueryString();

        return Inertia::render('orders/index', [
            'orders' => OrderListResource::collection($orders)->response()->getData(true),
            'stats' => fn () => $this->orderQuery->statusCounts($request, $request->user()),
            'filters' => $request->only([
                'tracking_number', 'order_number', 'customer_name', 'customer_phone',
                'seller', 'city_id', 'sector_id', 'status', 'status_group', 'payment_method',
                'created_from', 'created_to', 'delivery_from', 'delivery_to',
                'is_fragile', 'can_be_opened', 'sort', 'direction', 'per_page',
            ]),
            // Closures so Inertia can skip them entirely on partial reloads:
            // paging, sorting and filtering only ask for "orders".
            'filterOptions' => fn () => $this->filterOptions(),
            'can' => fn () => $this->abilities($request),
            'workflow' => fn () => $this->workflowOptions($request),
        ]);
    }

    /**
     * Status transitions the current user may apply, keyed by source status.
     *
     * Resolved once per page rather than per row: the transition graph only
     * depends on the order's current status, and the ownership half of the check
     * is answered by `can_update_status` on each row's policy check server side.
     *
     * @return array<string, mixed>
     */
    private function workflowOptions(Request $request): array
    {
        $user = $request->user();

        $transitions = collect(OrderTransitionService::transitionMap())
            ->map(fn (array $targets) => collect($targets)
                ->filter(fn (string $status) => $user->hasPermission('orders.transition.to_'.strtolower($status)))
                ->map(fn (string $status) => [
                    'value' => $status,
                    'label' => OrderStatus::from($status)->label(),
                    'color' => OrderStatus::from($status)->color(),
                    'icon' => OrderStatus::from($status)->icon(),
                    'requires_reason' => OrderStatus::from($status)->carriesFailureReason(),
                ])
                ->values()
                ->all())
            ->filter(fn (array $targets) => $targets !== [])
            ->all();

        return [
            'transitions' => $transitions,
            'failure_reasons' => OrderFailureReason::options(),
            // The delivery leg is reported as an outcome rather than picked
            // from the transition list: see OrderController::deliveryOutcome().
            'delivery_outcomes' => DeliveryOutcome::options(),
            'delivery_outcome_statuses' => DeliveryOutcomeService::reportableStatuses(),
            // Drivers only ever see orders assigned to them, so holding the
            // scoped permission is enough to enable the quick actions.
            'can_update_status' => $user->hasPermission('orders.update.all')
                || $user->hasPermission('orders.update.assigned'),
            'is_driver' => $user->isDriver(),
            // A driver who could not hand a parcel over opens the return from
            // his card: the detail screen that normally hosts that action is
            // closed to him.
            'can_create_return' => $user->canCreateDriverReturn(),
            'return_eligible_statuses' => ReturnService::eligibleOrderStatuses(
                ReturnInitiatedByRole::DRIVER
            ),
            'return_reasons' => ReturnReason::options(),
        ];
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Order::class);

        $cloneData = null;
        $sectors = [];

        if ($request->filled('clone')) {
            $source = Order::query()->findOrFail($request->integer('clone'));
            $this->authorize('view', $source);
            $cloneData = $this->orderService->clonePayload($source);

            if ($cloneData['city_id']) {
                $sectors = $this->sectorOptions((int) $cloneData['city_id']);
            }
        }

        $canUseStock = $request->user()->hasPermission(StockPermissions::ORDERS_CREATE_WITH_STOCK);

        return Inertia::render('orders/create', [
            'cities' => $this->cityOptions(),
            'sectors' => $sectors,
            'paymentMethods' => PaymentMethod::options(),
            'cloneData' => $cloneData,
            'canUseStock' => $canUseStock,
            // The catalog travels with the page rather than behind a search
            // endpoint: the pick-list has to answer a keystroke instantly, and a
            // vendor's catalog is orders of magnitude smaller than the sector
            // table this screen already ships.
            'products' => $canUseStock ? $this->pickableProducts() : [],
        ]);
    }

    /**
     * Catalog options for the order pick-list.
     *
     * Out-of-stock references are included on purpose: hiding them makes the
     * seller wonder whether he mistyped the name, while showing them disabled
     * answers the question he actually has.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pickableProducts(): array
    {
        return Product::query()
            ->active()
            ->orderBy('name')
            ->limit((int) config('stock.picklist_limit', 2000))
            ->get([
                'id', 'store_id', 'name', 'sku', 'barcode', 'category',
                'unit_price', 'stock_quantity', 'is_fragile', 'photo_path', 'blocked_at',
            ])
            ->map(fn (Product $product) => $product->toPickOption())
            ->all();
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $order = $this->storeOrder($request);

        return redirect()
            ->route('orders.show', $order)
            ->with('success', "Order {$order->tracking_number} created successfully.");
    }

    public function storeAndNew(StoreOrderRequest $request): RedirectResponse
    {
        $this->storeOrder($request);

        return redirect()
            ->route('orders.create')
            ->with('success', 'Order created successfully. You can create a new order.');
    }

    /**
     * Persist a new order using validated request data.
     */
    private function storeOrder(StoreOrderRequest $request): Order
    {
        $this->authorize('create', Order::class);

        return $this->orderService->create($request->validated(), $request->user());
    }

    public function show(Request $request, Order $order): Response
    {
        // Not `view`: a driver may read his assigned orders in the list without
        // being allowed to open the full detail screen.
        $this->authorize('viewDetails', $order);

        $order->load([
            'city',
            'sector',
            'store',
            'partner',
            'invoice',
            'driver.roles',
            'driverTransactions.driverInvoice',
            'seller.roles',
            'seller.city',
            'stockHubCity',
            'pickupRequest.createdBy.roles',
            'pickupRequest.assignedDriver.roles',
            'transfers' => fn ($q) => $q->where('transfers.status', '!=', TransferStatus::CANCELLED->value),
            'statusHistories.user.roles',
            'statusHistories.pickupRequest',
            'statusHistories.transfer',
            'statusHistories.orderReturn',
            'orderReturn.statusHistories',
            'changeHistories.changedByUser.roles',
            'items.product',
        ]);

        return Inertia::render('orders/show', [
            'order' => OrderResource::make($order)->resolve($request),
            'allowedTransitions' => $this->transitionOptions($order),
            // A parcel on the round is closed through the outcome flow instead
            // of the transition dropdown, so the page gets what that sheet needs.
            'deliveryOutcome' => [
                'reportable' => ! $order->isPartnerDelivery()
                    && DeliveryOutcomeService::isReportable($order)
                    && $request->user()->can('updateStatus', $order),
                'outcomes' => DeliveryOutcome::options(),
                'failure_reasons' => OrderFailureReason::options(),
            ],
            'returnFilterOptions' => ['reasons' => ReturnReason::options()],
            'can' => array_merge($this->abilities($request), [
                'update' => $request->user()->can('update', $order),
                'delete' => $request->user()->can('delete', $order),
                'print' => $request->user()->can('print', $order),
                'assign_driver' => app(OrderPolicy::class)->evaluateAssignDriver($request->user(), $order),
                'update_partner_status' => $order->partner_id && $request->user()->can('partner-delivery.update', $order),
                'request_return' => $this->canRequestReturn($request->user(), $order),
                'create_failed_return' => $this->canCreateFailedReturn($request->user(), $order),
                'view_partner' => $order->partner && $request->user()->can('view', $order->partner),
                'sync' => $order->partner_id && $order->partner && $request->user()->can('sync', $order->partner),
            ]),
            'driverOptions' => app(OrderPolicy::class)->evaluateAssignDriver($request->user(), $order)
                ? $this->driverAssignmentOptions()
                : [],
        ]);
    }

    /**
     * Pull the partner delivery for this order and refresh local data.
     */
    public function syncPartner(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $order);

        if (! $order->partner_id || ! $order->partner) {
            abort(422, __('partners.sync.order_not_partner'));
        }

        $this->authorize('sync', $order->partner);

        try {
            $this->partnerIngestion->syncOrder($order, $request->user());
            $message = __('partners.sync.order_success');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return back()->with('success', $message);
        } catch (PartnerApiException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request, Order $order): Response
    {
        $this->authorize('update', $order);

        $order->load(['city', 'sector', 'seller', 'seller.city', 'stockHubCity']);

        return Inertia::render('orders/edit', [
            'order' => OrderResource::make($order)->resolve($request),
            'cities' => $this->cityOptions(),
            'sectors' => $order->city_id ? $this->sectorOptions($order->city_id) : [],
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $this->orderService->update($order, $request->validated(), $request->user());

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Order updated successfully.');
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        // An order picked from the catalog took units off the shelf. Deleting it
        // has to put them back, otherwise the vendor's next inventory count is
        // short for a reason nobody can explain.
        $this->orderStock->detach($order, $request->user());

        $order->delete();

        return redirect()
            ->route('orders.index')
            ->with('success', 'Order deleted successfully.');
    }

    /**
     * Public tracking timeline reached by scanning the QR code (/orders/{trackingNumber}).
     */
    public function track(Request $request, string $trackingNumber): Response
    {
        $order = Order::query()
            ->where('tracking_number', $trackingNumber)
            ->with([
                'city',
                'sector',
                'statusHistories.user.roles',
                'statusHistories.pickupRequest',
                'statusHistories.transfer',
            ])
            ->firstOrFail();

        $this->authorize('view', $order);

        return Inertia::render('orders/tracking', [
            'order' => OrderResource::make($order)->resolve($request),
        ]);
    }

    /**
     * Stream / download the thermal shipping label.
     */
    public function pdf(Request $request, Order $order, OrderLabelPdfService $pdfService): HttpResponse
    {
        $this->authorize('print', $order);

        $pdf = $pdfService->build($order);
        $fileName = $pdfService->fileName($order);

        return $request->boolean('download')
            ? $pdf->download($fileName)
            : $pdf->stream($fileName);
    }

    /**
     * Export selected orders (or the current filter) as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export', Order::class);

        $query = $this->scopedQuery($request);

        if ($request->filled('ids')) {
            $query->whereIn('id', $this->ids($request));
        }

        $orders = $query->with(['city', 'sector', 'seller'])->orderByDesc('id')->get();

        $fileName = 'orders-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'wb');

            // Excel assumes the system codepage unless the file opens with a
            // UTF-8 BOM, which turns Arabic customer data into mojibake.
            fwrite($handle, "\u{FEFF}");

            fputcsv($handle, [
                'Tracking Number', 'Status', 'Customer', 'Phone', 'City', 'Sector',
                'Payment', 'Order Value', 'To Collect', 'Delivery Price', 'Total', 'Seller', 'Created At',
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->tracking_number,
                    $order->status->label(),
                    $order->customer_full_name,
                    $order->customer_phone,
                    $order->city?->name,
                    $order->sector?->name,
                    $order->payment_method->label(),
                    $order->order_value !== null ? number_format((float) $order->order_value, 2, '.', '') : '',
                    $order->order_amount !== null ? number_format((float) $order->order_amount, 2, '.', '') : '',
                    number_format((float) $order->delivery_price, 2, '.', ''),
                    number_format((float) $order->total_amount, 2, '.', ''),
                    $order->seller?->full_name,
                    $order->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Print a batch of shipping labels as a single PDF.
     */
    public function labels(Request $request, OrderLabelPdfService $pdfService): HttpResponse
    {
        $this->authorize('print', Order::class);

        $orders = $this->scopedQuery($request)
            ->whereIn('id', $this->ids($request))
            ->with(['city', 'seller'])
            ->get();

        abort_if($orders->isEmpty(), HttpResponse::HTTP_NOT_FOUND, 'No orders selected.');

        return $pdfService->buildBatch($orders)->stream('labels-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Assign (or reassign) a driver to an order that is out for delivery.
     */
    public function assignDriver(AssignOrderDriverRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('assignDriver', $order);

        if (! $this->driverAssignment->canAssign($order, $request->user())) {
            throw ValidationException::withMessages([
                'driver_id' => __('driver_invoices.assign.not_allowed'),
            ]);
        }

        $driver = User::query()->findOrFail($request->integer('driver_id'));

        $this->driverAssignment->assign($order, $driver, $request->user());

        return back()->with('success', __('driver_invoices.assign.assigned'));
    }

    /**
     * Close a delivery attempt: delivered, or not delivered and why.
     *
     * Kept apart from `bulkStatus` because the driver does not pick a status
     * here — he reports what happened, and the failure reason decides whether
     * the parcel leaves the round or stays on it for another try.
     */
    public function deliveryOutcome(StoreDeliveryOutcomeRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('updateStatus', $order);

        if ($order->isPartnerDelivery()) {
            return back()->with('error', __('partners.orders.sync.use_partner_endpoint'));
        }

        $order = $this->deliveryOutcome->record(
            $order,
            $request->user(),
            $request->outcome(),
            $request->failureReason(),
            $request->input('note'),
            $request->file('attachment'),
        );

        return back()->with('success', $this->deliveryOutcomeMessage($order, $request->outcome()));
    }

    private function deliveryOutcomeMessage(Order $order, DeliveryOutcome $outcome): string
    {
        if ($outcome === DeliveryOutcome::DELIVERED) {
            return __('orders.delivery_outcome.flash.delivered', ['tracking' => $order->tracking_number]);
        }

        return $order->status === OrderStatus::READY_TO_RETURN
            ? __('orders.delivery_outcome.flash.ready_to_return', ['tracking' => $order->tracking_number])
            : __('orders.delivery_outcome.flash.attempt_recorded', ['count' => $order->failed_attempts_count]);
    }

    /**
     * Apply a status transition to several orders at once.
     */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'to_status' => ['required', 'string', Rule::in(OrderStatus::values())],
            'comment' => ['nullable', 'string', 'max:1000'],
            'failure_reason' => [
                Rule::requiredIf(fn () => OrderStatus::tryFrom(
                    (string) $request->input('to_status')
                )?->carriesFailureReason() === true),
                'nullable',
                'string',
                Rule::in(OrderFailureReason::values()),
            ],
            'failure_note' => ['nullable', 'string', 'max:500'],
        ], [
            'failure_reason.required' => __('orders.failure.reason_required'),
        ]);

        $partnerOrderIds = Order::query()
            ->whereIn('id', $validated['ids'])
            ->whereNotNull('partner_id')
            ->pluck('id');

        if ($partnerOrderIds->isNotEmpty()) {
            return back()->with('error', __('partners.orders.sync.use_partner_endpoint'));
        }

        $orders = $this->scopedQuery($request)->whereIn('id', $validated['ids'])->get();

        $updated = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            try {
                $this->authorize('updateStatus', $order);
                $this->transitionService->transition(
                    $order,
                    $validated['to_status'],
                    $request->user(),
                    $validated['comment'] ?? null,
                    [
                        'failure_reason' => $validated['failure_reason'] ?? null,
                        'failure_note' => $validated['failure_note'] ?? null,
                    ]
                );
                $updated++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        return back()->with(
            'success',
            "Status updated for {$updated} order(s)".($skipped ? ", {$skipped} skipped (not allowed)." : '.')
        );
    }

    /**
     * Orders the current user is allowed to act on.
     */
    private function scopedQuery(Request $request): Builder
    {
        $query = Order::query()->whereNull('partner_id');

        $user = $request->user();

        if ($user->hasPermission('orders.read.all')) {
            // full access
        } elseif ($user->hasPermission('orders.read.assigned')) {
            $query->assignedTo($user->id);
        } elseif ($user->hasPermission('orders.read.own')) {
            $query->ownedBy($user->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * @return array<int, int>
     */
    private function ids(Request $request): array
    {
        return collect(explode(',', (string) $request->input('ids', '')))
            ->merge((array) $request->input('ids'))
            ->flatten()
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'statuses' => OrderStatus::options(),
            'paymentMethods' => PaymentMethod::options(),
            'cities' => $this->cityOptions(),
            'pageSizes' => [10, 25, 50, 100],
            // Labels for the sidebar shortcuts, so the list can name the view
            // it is currently restricted to.
            'statusGroups' => array_map(
                static fn (string $group): array => [
                    'value' => $group,
                    'label' => __('orders.views.'.$group),
                ],
                array_keys(OrderQueryService::STATUS_GROUPS)
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cityOptions(): array
    {
        return array_map(
            static fn (array $city) => [
                'id' => $city['id'],
                'name' => $city['name'],
                'region' => $city['region'],
            ],
            City::options()
        );
    }

    /**
     * Active sectors for a given city, used to pre-fill the dependent dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sectorOptions(int $cityId): array
    {
        return Sector::query()
            ->forCity($cityId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'city_id', 'name', 'delivery_price'])
            ->map(fn (Sector $sector) => [
                'id' => $sector->id,
                'city_id' => $sector->city_id,
                'name' => $sector->name,
                'delivery_price' => (float) $sector->delivery_price,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function transitionOptions(Order $order): array
    {
        $user = request()->user();

        if ($order->partner_id) {
            if (! $user->can('partner-delivery.update', $order)) {
                return [];
            }

            // A partner delivery is not governed by our transition graph: the
            // partner's own vocabulary is authoritative and PartnerDeliveryService
            // lets an operator jump freely inside it to mirror whatever the
            // partner reports. Deriving the list from the native graph would
            // hide states the partner still uses — "en attente de retour" chief
            // among them, since FAILED left the graph with the outcome flow.
            $current = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

            return collect(PartnerOrderStatus::values())
                ->reject(fn (string $status) => $status === $current)
                ->map(fn (string $status) => [
                    'value' => $status,
                    'label' => OrderStatus::from($status)->label(),
                    'color' => OrderStatus::from($status)->color(),
                ])
                ->values()
                ->all();
        }

        // Ownership first (ABAC): offering a transition the policy will refuse
        // produced buttons that always failed for drivers and dispatchers.
        if (! $user->can('updateStatus', $order)) {
            return [];
        }

        // The two ways a delivery can end belong to the outcome flow, which
        // asks for the reason that decides between them. Leaving them in the
        // dropdown would offer a second, reason-less route to the same states.
        $ownedByOutcomeFlow = DeliveryOutcomeService::isReportable($order)
            ? [OrderStatus::DELIVERED->value, OrderStatus::READY_TO_RETURN->value]
            : [];

        return collect($this->transitionService->allowedNextStatuses($order))
            ->reject(fn (string $status) => in_array($status, $ownedByOutcomeFlow, true))
            ->filter(fn (string $status) => $user->hasPermission('orders.transition.to_'.strtolower($status)))
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => OrderStatus::from($status)->label(),
                'color' => OrderStatus::from($status)->color(),
                'requires_reason' => OrderStatus::from($status)->carriesFailureReason(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, email: string|null}>
     */
    private function driverAssignmentOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::DRIVER))
            ->orderBy('first_name')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email'])
            ->map(fn (User $driver) => [
                'id' => $driver->id,
                'name' => $driver->full_name,
                'email' => $driver->email,
            ])
            ->all();
    }

    /**
     * UI capability flags.
     *
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'create' => $user->hasPermission('orders.create'),
            'read_all' => $user->hasPermission('orders.read.all'),
            'export' => $user->hasPermission('orders.export'),
            'print' => $user->hasPermission('orders.print'),
            // Drives whether the list renders links to the detail screen at all,
            // mirroring OrderPolicy::viewDetails().
            'view_details' => OrderPolicy::grantsDetailAccess($user),
        ];
    }

    private function canRequestReturn($user, Order $order): bool
    {
        if (! $user->canCreateReturnRequest() || $order->activeReturn()) {
            return false;
        }

        if ($order->seller_id !== $user->id) {
            return false;
        }

        $status = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        return in_array($status, ReturnService::eligibleOrderStatuses(
            ReturnInitiatedByRole::SELLER
        ), true);
    }

    private function canCreateFailedReturn($user, Order $order): bool
    {
        if (! $user->canCreateDriverReturn() || $order->activeReturn()) {
            return false;
        }

        $status = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        return in_array($status, ReturnService::eligibleOrderStatuses(
            ReturnInitiatedByRole::DRIVER
        ), true);
    }
}
