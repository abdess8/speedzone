<?php

namespace App\Http\Controllers;

use App\Enums\StockAdjustmentReason;
use App\Http\Requests\StoreStockAdjustmentsRequest;
use App\Http\Resources\ProductListResource;
use App\Models\Product;
use App\Services\StockInventoryService;
use App\Support\CountingContext;
use App\Support\StockPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mass inventory: counting a shelf and recording what was found.
 *
 * The screen is a high-density editable table rather than one form per product,
 * because an inventory is done in one pass with a scanner in one hand — sixty
 * round trips through a detail page is not a workflow.
 */
class StockInventoryController extends Controller
{
    public function __construct(
        private readonly StockInventoryService $inventoryService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->active()
            ->search($request->input('search'))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->input('category')))
            ->when($request->input('stock_status') === 'out', fn ($query) => $query->outOfStock())
            ->when($request->input('stock_status') === 'in', fn ($query) => $query->inStock())
            ->select(ProductListResource::COLUMNS)
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('stock/inventory/index', [
            'products' => ProductListResource::collection($products)->response()->getData(true),
            'filters' => $request->only(['search', 'category', 'stock_status', 'per_page']),
            'reasons' => StockAdjustmentReason::options(),
            'summary' => $this->inventoryService->summary(),
            'can' => [
                'adjust' => $request->user()->hasPermission(StockPermissions::ADJUST)
                    || $request->user()->hasPermission(StockPermissions::ADMIN_OVERRIDE),
            ],
        ]);
    }

    /**
     * Record a reconciliation sheet.
     *
     * Only the lines the counter actually touched reach this endpoint. Of those,
     * only the ones whose count differs from the recorded quantity reach the
     * ledger — an inventory that confirms the screen is not a movement. Every
     * line is nonetheless stamped on the product sheet with its author, its
     * machine and, when the browser offers one, its position: the fact that
     * somebody verified a reference is itself worth keeping.
     */
    public function store(StoreStockAdjustmentsRequest $request): RedirectResponse
    {
        // Authorised per product rather than globally: a hub agent holding
        // stock.admin_override may correct any shelf, a vendor only his own.
        $products = Product::query()
            ->whereKey(array_column($request->lines(), 'product_id'))
            ->get();

        foreach ($products as $product) {
            $this->authorize('adjust', $product);
        }

        $lines = $request->lines();

        $adjustments = $this->inventoryService->apply(
            $lines,
            $request->user(),
            CountingContext::fromRequest($request, $request->location()),
        );

        return redirect()
            ->back()
            ->with('success', __('stock.inventory.flash.saved', [
                'counted' => count($lines),
                'corrections' => $adjustments->count(),
            ]));
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 50), 10), 200);
    }
}
