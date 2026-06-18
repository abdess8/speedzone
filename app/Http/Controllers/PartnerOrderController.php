<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PartnerOrderStatus;
use App\Http\Requests\PartnerOrderBulkAssignDriverRequest;
use App\Http\Requests\PartnerOrderBulkIdsRequest;
use App\Http\Requests\PartnerOrderBulkScanRequest;
use App\Http\Requests\PartnerOrderScanRequest;
use App\Http\Resources\OrderResource;
use App\Models\City;
use App\Models\Order;
use App\Models\Partner;
use App\Services\PartnerOrderBulkService;
use App\Services\PartnerOrderQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerOrderController extends Controller
{
    public function __construct(
        private readonly PartnerOrderQueryService $partnerOrders,
        private readonly PartnerOrderBulkService $bulkService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewOrders', Partner::class);

        $orders = $this->partnerOrders->build($request, $request->user())
            ->paginate($this->partnerOrders->perPage($request))
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('partner-orders/index', [
            'orders' => OrderResource::collection($orders)->response()->getData(true),
            'filters' => $request->only([
                'tracking_number', 'customer_name', 'customer_phone',
                'partner_id', 'city_id', 'status',
                'created_from', 'created_to', 'sort', 'direction', 'per_page',
            ]),
            'filterOptions' => [
                'statuses' => PartnerOrderStatus::options(),
                'cities' => City::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'partners' => $this->partnerOrders->partnerOptions($user),
                'drivers' => $user->can('manageDeliveries', Partner::class)
                    ? $this->bulkService->driverOptions()
                    : [],
                'pageSizes' => [10, 25, 50, 100],
            ],
            'can' => $this->abilities($user),
        ]);
    }

    public function scan(PartnerOrderScanRequest $request): JsonResponse
    {
        $this->authorize('manageDeliveries', Partner::class);

        $result = $this->bulkService->validateScan(
            $request->user(),
            $request->string('tracking_number')->toString()
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function bulkAdvanceStatus(PartnerOrderBulkIdsRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('manageDeliveries', Partner::class);

        $result = $this->bulkService->advanceToNextStatus(
            $request->user(),
            $request->input('ids')
        );

        $message = __('partners.orders.bulk_status_result', [
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        }

        return back()->with('success', $message);
    }

    public function bulkAssignDriver(PartnerOrderBulkAssignDriverRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('manageDeliveries', Partner::class);

        $result = $this->bulkService->assignDriver(
            $request->user(),
            $request->input('ids'),
            $request->integer('driver_id')
        );

        $message = __('partners.orders.bulk_assign_result', [
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        }

        return back()->with('success', $message);
    }

    public function bulkScan(PartnerOrderBulkScanRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('manageDeliveries', Partner::class);

        $result = $this->bulkService->bulkScanAdvance(
            $request->user(),
            $request->input('orders')
        );

        $message = __('partners.orders.bulk_scan_result', [
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'updated' => $result['updated'],
                'data' => $result,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * @return array<string, bool>
     */
    private function abilities($user): array
    {
        return [
            'sync' => $user->hasPermission('partners.sync'),
            'view_partners' => $user->hasPermission('partners.read'),
            'manage_deliveries' => $user->can('manageDeliveries', Partner::class),
            'assign_driver' => $user->hasPermission('driver_invoices.assign_driver')
                || $user->hasPermission('partners.deliveries.manage'),
            'bulk_status' => $user->can('manageDeliveries', Partner::class),
            'scan' => $user->can('manageDeliveries', Partner::class),
        ];
    }
}
