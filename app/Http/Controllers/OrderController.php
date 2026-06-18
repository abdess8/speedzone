<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReturnReason;
use App\Http\Requests\AssignOrderDriverRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\City;
use App\Models\Order;
use App\Models\Sector;
use App\Models\User;
use App\Services\OrderDriverAssignmentService;
use App\Services\OrderLabelPdfService;
use App\Services\OrderQueryService;
use App\Services\OrderService;
use App\Services\OrderTransitionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $orders = $this->orderQuery->build($request, $request->user())
            ->paginate($this->orderQuery->perPage($request))
            ->withQueryString();

        return Inertia::render('orders/index', [
            'orders' => OrderResource::collection($orders)->response()->getData(true),
            'filters' => $request->only([
                'tracking_number', 'order_number', 'customer_name', 'customer_phone',
                'seller', 'city_id', 'sector_id', 'status', 'payment_method',
                'created_from', 'created_to', 'delivery_from', 'delivery_to',
                'is_fragile', 'can_be_opened', 'sort', 'direction', 'per_page',
            ]),
            'filterOptions' => $this->filterOptions(),
            'can' => $this->abilities($request),
        ]);
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

        return Inertia::render('orders/create', [
            'cities' => $this->cityOptions(),
            'sectors' => $sectors,
            'paymentMethods' => PaymentMethod::options(),
            'cloneData' => $cloneData,
        ]);
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
        $this->authorize('view', $order);

        $order->load([
            'city',
            'sector',
            'invoice',
            'driver.roles',
            'driverTransactions.driverInvoice',
            'seller.roles',
            'seller.city',
            'pickupRequest.createdBy.roles',
            'pickupRequest.assignedDriver.roles',
            'transfers' => fn ($q) => $q->where('transfers.status', '!=', \App\Enums\TransferStatus::CANCELLED->value),
            'statusHistories.user.roles',
            'statusHistories.pickupRequest',
            'statusHistories.transfer',
            'statusHistories.orderReturn',
            'orderReturn.statusHistories',
            'changeHistories.changedByUser.roles',
        ]);

        return Inertia::render('orders/show', [
            'order' => OrderResource::make($order)->resolve($request),
            'allowedTransitions' => $this->transitionOptions($order),
            'returnFilterOptions' => ['reasons' => ReturnReason::options()],
            'can' => array_merge($this->abilities($request), [
                'update' => $request->user()->can('update', $order),
                'delete' => $request->user()->can('delete', $order),
                'print' => $request->user()->can('print', $order),
                'assign_driver' => $request->user()->can('assignDriver', $order),
                'request_return' => $this->canRequestReturn($request->user(), $order),
                'create_failed_return' => $this->canCreateFailedReturn($request->user(), $order),
            ]),
            'driverOptions' => $request->user()->can('assignDriver', $order)
                ? $this->driverAssignmentOptions()
                : [],
        ]);
    }

    public function edit(Request $request, Order $order): Response
    {
        $this->authorize('update', $order);

        $order->load(['city', 'sector', 'seller', 'seller.city']);

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
        }, $fileName, ['Content-Type' => 'text/csv']);
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

        $driver = User::query()->findOrFail($request->integer('driver_id'));

        $this->driverAssignment->assign($order, $driver, $request->user());

        return back()->with('success', __('driver_invoices.assign.assigned'));
    }

    /**
     * Apply a status transition to several orders at once.
     */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'to_status' => ['required', 'string'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $orders = $this->scopedQuery($request)->whereIn('id', $validated['ids'])->get();

        $updated = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            try {
                $this->authorize('update', $order);
                $this->transitionService->transition(
                    $order,
                    $validated['to_status'],
                    $request->user(),
                    $validated['comment'] ?? null
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

        if (! $request->user()->hasPermission('orders.read.all')) {
            $query->ownedBy($request->user()->id);
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
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cityOptions(): array
    {
        return City::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'region'])
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
                'region' => $city->region,
            ])
            ->all();
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

        return collect($this->transitionService->allowedNextStatuses($order))
            ->filter(fn (string $status) => $user->hasPermission('orders.transition.to_'.strtolower($status)))
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => OrderStatus::from($status)->label(),
                'color' => OrderStatus::from($status)->color(),
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
            ->whereHas('roles', fn ($q) => $q->where('name', \App\Models\Role::DRIVER))
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

        return in_array($status, \App\Services\ReturnService::eligibleOrderStatuses(
            \App\Enums\ReturnInitiatedByRole::SELLER
        ), true);
    }

    private function canCreateFailedReturn($user, Order $order): bool
    {
        if (! $user->canCreateDriverReturn() || $order->activeReturn()) {
            return false;
        }

        $status = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        return in_array($status, \App\Services\ReturnService::eligibleOrderStatuses(
            \App\Enums\ReturnInitiatedByRole::DRIVER
        ), true);
    }
}
