<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkScanPreparationRequest;
use App\Http\Requests\PrepareOrdersRequest;
use App\Http\Requests\ScanPreparationRequest;
use App\Http\Resources\PreparationOrderResource;
use App\Models\City;
use App\Services\OrderPreparationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The picking bench.
 *
 * Every route here is gated on `orders.transition.to_prepared` in the route
 * definition; the service then re-checks each order one by one, because a queue
 * spans vendors and an agent may legitimately see a parcel he cannot touch.
 */
class OrderPreparationController extends Controller
{
    public function __construct(
        private readonly OrderPreparationService $preparation,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['hub_city_id', 'search']);

        $orders = $this->preparation
            ->queue($request, $filters)
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('orders/preparation/index', [
            'orders' => PreparationOrderResource::collection($orders)->response()->getData(true),
            'stats' => $this->preparation->statusCounts($filters),
            'filters' => array_merge($filters, $this->preparation->sortState($request)),
            'hubCities' => City::hubOptions(),
        ]);
    }

    /**
     * Pack the boxes ticked on screen.
     */
    public function prepare(PrepareOrdersRequest $request): RedirectResponse
    {
        $result = $this->preparation->prepareByIds($request->user(), $request->ids());

        if ($result['prepared'] === 0) {
            return back()->with('error', __('preparation.flash.none_prepared'));
        }

        return back()->with('success', __('preparation.flash.prepared', $result));
    }

    /**
     * Vet one label so the scanner can show green or red before the agent commits.
     */
    public function scan(ScanPreparationRequest $request): JsonResponse
    {
        return response()->json(
            $this->preparation->validateScan($request->user(), $request->string('tracking_number')->toString())
        );
    }

    /**
     * Pack everything on the trolley.
     */
    public function bulkScan(BulkScanPreparationRequest $request): JsonResponse
    {
        $result = $this->preparation->prepareByTracking($request->user(), $request->trackingNumbers());

        return response()->json($result);
    }
}
