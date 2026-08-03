<?php

namespace App\Http\Controllers;

use App\Enums\TransferContentType;
use App\Enums\TransferStatus;
use App\Http\Requests\AssignTransferStaffRequest;
use App\Http\Requests\ChangeTransferStatusRequest;
use App\Http\Requests\EligibleTransferOrdersRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\TransferBulkReceiveRequest;
use App\Http\Requests\TransferScanRequest;
use App\Http\Requests\UpdateTransferRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderReturnResource;
use App\Http\Resources\TransferResource;
use App\Models\City;
use App\Models\Transfer;
use App\Services\TransferQrCodeService;
use App\Services\TransferQueryService;
use App\Services\TransferScanService;
use App\Services\TransferService;
use App\Services\TransferTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly TransferQueryService $transferQuery,
        private readonly TransferTransitionService $transitions,
        private readonly TransferScanService $scanService,
        private readonly TransferQrCodeService $qrCodes,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transfer::class);

        $user = $request->user();
        $transfers = $this->transferQuery->build($request, $user)
            ->paginate($this->transferQuery->perPage($request))
            ->withQueryString();

        return Inertia::render('transfers/index', [
            'transfers' => TransferResource::collection($transfers)->response()->getData(true),
            'filters' => $request->only([
                'search', 'status', 'from_city_id', 'to_city_id', 'created_from', 'created_to', 'per_page',
            ]),
            'filterOptions' => [
                'statuses' => TransferStatus::options(),
                'contentTypes' => TransferContentType::options(),
                'cities' => City::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
                'pageSizes' => [10, 15, 25, 50],
                'defaultFromCityId' => $this->defaultFromCityId(),
            ],
            'eligibleOrders' => [],
            'staff' => $user->hasPermission('transfers.create')
                ? $this->transfers->staffOptions()->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->full_name,
                    'phone' => $u->phone_number,
                ])->all()
                : [],
            'can' => $this->abilities($request),
        ]);
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

    public function eligibleReturns(EligibleTransferOrdersRequest $request): JsonResponse
    {
        $returns = $this->transfers->getEligibleReturns(
            $request->integer('from_city_id'),
            $request->integer('to_city_id'),
            $request->only([
                'search',
                'customer',
                'created_from',
                'created_to',
            ])
        );

        return response()->json([
            'data' => OrderReturnResource::collection($returns)->resolve($request),
        ]);
    }

    public function store(StoreTransferRequest $request): RedirectResponse
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

        return redirect()
            ->route('transfers.show', $transfer)
            ->with('success', "Transfer {$transfer->reference} created successfully.");
    }

    public function show(Request $request, Transfer $transfer): Response
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
            'returns.order.seller.city',
            'returns.order.city',
            'returns.currentLocationCity',
            'statusHistories.changedBy.roles',
        ]);

        return Inertia::render('transfers/show', [
            'transfer' => TransferResource::make($transfer)->resolve($request),
            'allowedTransitions' => $this->transitionOptions($transfer, $request->user()),
            'qrCode' => $request->user()->can('printQr', $transfer)
                ? $this->qrCodes->dataUri($transfer->reference)
                : null,
            'staff' => $request->user()->can('assignStaff', $transfer)
                ? $this->transfers->staffOptions()->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->full_name,
                    'phone' => $u->phone_number,
                ])->all()
                : [],
            'can' => array_merge($this->abilities($request), [
                'view' => true,
                'update' => $request->user()->can('update', $transfer),
                'assign' => $request->user()->can('assignStaff', $transfer),
                'dispatch' => $request->user()->can('dispatch', $transfer),
                'receive' => $request->user()->can('receive', $transfer),
                'scan' => $request->user()->can('scan', $transfer),
                'print_qr' => $request->user()->can('printQr', $transfer),
            ]),
        ]);
    }

    public function update(UpdateTransferRequest $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('update', $transfer);

        $this->transfers->update($transfer, $request->user(), $request->validated());

        return redirect()
            ->route('transfers.show', $transfer)
            ->with('success', 'Transfer updated successfully.');
    }

    public function assignStaff(AssignTransferStaffRequest $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('assignStaff', $transfer);

        $this->transfers->assignStaff(
            $transfer,
            $request->user(),
            $request->integer('assigned_to')
        );

        return redirect()
            ->route('transfers.show', $transfer)
            ->with('success', 'Staff assigned successfully.');
    }

    public function dispatch(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('dispatch', $transfer);

        $this->transitions->dispatch($transfer, $request->user(), $request->input('comment'));

        return back()->with('success', 'Transfer dispatched successfully.');
    }

    public function receive(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('receive', $transfer);

        $this->transitions->receive($transfer, $request->user(), $request->input('comment'));

        return back()->with('success', 'Transfer received successfully.');
    }

    public function changeStatus(ChangeTransferStatusRequest $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('changeStatus', $transfer);

        $this->transitions->transition(
            $transfer,
            $request->string('status')->toString(),
            $request->user(),
            $request->input('comment')
        );

        return back()->with('success', 'Transfer status updated successfully.');
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

    public function bulkReceive(TransferBulkReceiveRequest $request, Transfer $transfer): RedirectResponse|JsonResponse
    {
        $result = $this->scanService->bulkReceive(
            $request->user(),
            $transfer,
            $request->input('orders', [])
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'updated' => $result['updated'],
                'transfer_completed' => $result['transfer_completed'],
                'orders' => $result['orders']->pluck('tracking_number'),
            ]);
        }

        $message = $result['transfer_completed']
            ? "All {$result['updated']} order(s) received — transfer completed."
            : "Received {$result['updated']} order(s).";

        return back()->with('success', $message);
    }

    public function qr(Transfer $transfer): HttpResponse
    {
        $this->authorize('printQr', $transfer);

        return response($this->qrCodes->svg($transfer->reference, 300), 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }

    /**
     * Public scan landing — resolves transfer reference from QR code URL.
     */
    public function track(string $reference): Response|RedirectResponse
    {
        $transfer = Transfer::query()
            ->where('reference', strtoupper($reference))
            ->with(['fromCity', 'toCity', 'orders'])
            ->firstOrFail();

        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $this->authorize('view', $transfer);

        return redirect()->route('transfers.show', $transfer);
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function transitionOptions(Transfer $transfer, $user): array
    {
        return collect($this->transitions->allowedNextStatuses($transfer, $user))
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => TransferStatus::from($status)->label(),
                'color' => TransferStatus::from($status)->color(),
            ])
            ->values()
            ->all();
    }

    private function defaultFromCityId(): ?int
    {
        $configured = config('transfer.default_from_city_id');

        if ($configured) {
            return (int) $configured;
        }

        return City::query()->active()->orderBy('id')->value('id');
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'create' => $user->can('create', Transfer::class),
            'read_all' => $user->hasPermission('transfers.read'),
            'update' => $user->hasPermission('transfers.update'),
            'dispatch' => $user->hasPermission('transfers.dispatch'),
            'receive' => $user->hasPermission('transfers.receive'),
        ];
    }
}
