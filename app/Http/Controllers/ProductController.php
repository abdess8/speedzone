<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductListResource;
use App\Models\Product;
use App\Services\ProductAuditService;
use App\Services\ProductService;
use App\Services\StockInventoryService;
use App\Support\SortableQuery;
use App\Support\StockPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor product catalog.
 *
 * Reads are filtered by the `store` global scope, so nothing here needs to
 * remember whose catalog it is looking at. Staff accounts are bound to no store
 * and therefore see every vendor's references — which is the point of
 * `stock.receive_inbound` and `stock.admin_override`.
 */
class ProductController extends Controller
{
    private const DEFAULT_SORT = 'name';

    public function __construct(
        private readonly ProductService $productService,
        private readonly StockInventoryService $inventoryService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $query = $this->filtered($request)->select(ProductListResource::COLUMNS);

        SortableQuery::apply($query, $request, self::sortable(), self::DEFAULT_SORT, 'asc');

        $products = $query
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('stock/products/index', [
            'products' => ProductListResource::collection($products)->response()->getData(true),
            'filters' => array_merge(
                $request->only(['search', 'category', 'stock_status', 'status', 'per_page']),
                SortableQuery::state($request, self::sortable(), self::DEFAULT_SORT, 'asc'),
            ),
            'categories' => $this->productService->categories(),
            'summary' => $this->inventoryService->summary(),
            'can' => $this->abilities($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('stock/products/create', [
            'categories' => $this->productService->categories(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->create(
            $request->productData(),
            $request->user(),
            $request->file('photo')
        );

        return redirect()
            ->route('products.index')
            ->with('success', __('stock.products.flash.created', ['sku' => $product->sku]));
    }

    /**
     * Product sheet with its audit trail and its movement ledger.
     */
    public function show(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);

        $product->load([
            'histories.author:id,first_name,last_name,name',
            'blockedBy:id,first_name,last_name,name',
        ]);

        return Inertia::render('stock/products/show', [
            'product' => ProductListResource::make($product)->resolve($request),
            'detail' => [
                'description' => $product->description,
                'weight_grams' => $product->weight_grams,
                'length_cm' => $product->length_cm !== null ? (float) $product->length_cm : null,
                'width_cm' => $product->width_cm !== null ? (float) $product->width_cm : null,
                'height_cm' => $product->height_cm !== null ? (float) $product->height_cm : null,
                'blocked_by' => $product->blockedBy?->full_name,
                'blocked_at' => $product->blocked_at?->toIso8601String(),
            ],
            'history' => $product->histories->map(fn ($entry) => [
                'id' => $entry->id,
                'field' => $entry->field_name,
                'field_label' => ProductAuditService::fieldLabel($entry->field_name),
                'old_value' => $entry->old_value,
                'new_value' => $entry->new_value,
                'author' => $entry->author?->full_name,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->all(),
            'movements' => $this->movements($product),
            'counts' => $this->inventoryCounts($product),
            'receptions' => $this->receptions($product),
            'can' => [
                'update' => $request->user()->can('update', $product),
                'adjust' => $request->user()->can('adjust', $product),
                'block' => $request->user()->can('block', $product),
                'delete' => $request->user()->can('delete', $product),
            ],
        ]);
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('stock/products/edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'category' => $product->category,
                'description' => $product->description,
                'photo_url' => $product->photo_url,
                'unit_price' => (float) $product->unit_price,
                'cost_price' => $product->cost_price !== null ? (float) $product->cost_price : null,
                'is_fragile' => (bool) $product->is_fragile,
                'is_active' => (bool) $product->is_active,
                'weight_grams' => $product->weight_grams,
                'length_cm' => $product->length_cm !== null ? (float) $product->length_cm : null,
                'width_cm' => $product->width_cm !== null ? (float) $product->width_cm : null,
                'height_cm' => $product->height_cm !== null ? (float) $product->height_cm : null,
                'stock_quantity' => (int) $product->stock_quantity,
            ],
            'categories' => $this->productService->categories(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->productService->update(
            $product,
            $request->productData(),
            $request->user(),
            $request->file('photo')
        );

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('stock.products.flash.updated'));
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product, $request->user());

        return redirect()
            ->route('products.index')
            ->with('success', __('stock.products.flash.archived'));
    }

    /**
     * Columns the catalog may be ordered on.
     *
     * The status column is left out: it collapses "blocked", "archived", "out of
     * stock" and "low" into one badge, and there is no ranking of those four a
     * reader would agree with in advance.
     *
     * @return array<string, string|array<int, mixed>>
     */
    private static function sortable(): array
    {
        return [
            'name' => 'name',
            'sku' => 'sku',
            'category' => 'category',
            'unit_price' => 'unit_price',
            // Same definition as Product::margin(): null — and therefore last
            // going down — for a vendor who does not track his costs.
            'margin' => [DB::raw('(unit_price - cost_price)')],
            'stock_quantity' => 'stock_quantity',
        ];
    }

    /**
     * Apply the catalog filters.
     */
    private function filtered(Request $request): Builder
    {
        $query = Product::query()->search($request->input('search'));

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        match ($request->input('stock_status')) {
            'out' => $query->outOfStock(),
            'low' => $query
                ->where('stock_quantity', '>', 0)
                ->where('stock_quantity', '<=', (int) config('stock.low_stock_threshold', 5)),
            'in' => $query->inStock(),
            'blocked' => $query->blocked(),
            default => null,
        };

        match ($request->input('status')) {
            'active' => $query->active(),
            'archived' => $query->where('is_active', false),
            default => null,
        };

        // Staff reading across vendors need to know whose reference they are
        // looking at; a vendor already knows.
        if ($request->user()->hasPermission(StockPermissions::ADMIN_OVERRIDE)
            || $request->user()->hasPermission(StockPermissions::RECEIVE_INBOUND)) {
            $query->with('seller:id,first_name,last_name,name');
        }

        return $query;
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 25), 10), 100);
    }

    /**
     * Recent movements of a product, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function movements(Product $product): array
    {
        return $product->adjustments()
            ->with(['author:id,first_name,last_name,name', 'reception:id,reference', 'order:id,tracking_number'])
            ->limit(100)
            ->get()
            ->map(fn ($movement) => [
                'id' => $movement->id,
                'source' => $movement->source->value,
                'source_label' => $movement->source->label(),
                'source_color' => $movement->source->color(),
                'source_icon' => $movement->source->icon(),
                'reason' => $movement->reason?->value,
                'reason_label' => $movement->reason?->label(),
                'reason_color' => $movement->reason?->color(),
                'note' => $movement->note,
                'stock_before' => $movement->stock_before,
                'stock_after' => $movement->stock_after,
                'delta' => $movement->delta,
                'author' => $movement->author?->full_name,
                'reception' => $movement->reception?->reference,
                'order' => $movement->order?->tracking_number,
                'created_at' => $movement->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Every verification of this reference, newest first.
     *
     * Includes the counts that changed nothing, which are the majority and the
     * whole reason this list exists next to the movement ledger: "last counted
     * three weeks ago" is an answer the ledger cannot give.
     *
     * @return array<int, array<string, mixed>>
     */
    private function inventoryCounts(Product $product): array
    {
        return $product->inventoryCounts()
            ->with('author:id,first_name,last_name,name')
            ->limit(100)
            ->get()
            ->map(fn ($count) => [
                'id' => $count->id,
                'counted_quantity' => $count->counted_quantity,
                'stock_before' => $count->stock_before,
                'delta' => $count->delta,
                'author' => $count->author?->full_name,
                'device' => $count->device_label,
                'ip_address' => $count->ip_address,
                'latitude' => $count->latitude,
                'longitude' => $count->longitude,
                'location_accuracy_m' => $count->location_accuracy_m,
                'created_at' => $count->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Inbound shipments carrying this reference, the ones still travelling first.
     *
     * A vendor looking at an empty shelf asks one question before any other:
     * is more of it already on its way? Answering it on the product sheet saves
     * him from opening every open slip to find out.
     *
     * @return array<int, array<string, mixed>>
     */
    private function receptions(Product $product): array
    {
        return $product->receptionItems()
            ->whereHas('reception')
            ->with(['reception.destinationCity:id,name'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function ($item) {
                $reception = $item->reception;
                $status = $reception->statusEnum();

                return [
                    'id' => $reception->id,
                    'reference' => $reception->reference,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_color' => $status->color(),
                    'status_icon' => $status->icon(),
                    'in_progress' => ! $status->isTerminal(),
                    'destination_city' => $reception->destinationCity?->name,
                    'quantity_sent' => (int) $item->quantity_sent,
                    'quantity_collected' => $item->quantity_collected === null ? null : (int) $item->quantity_collected,
                    'quantity_received' => (int) $item->quantity_received,
                    'quantity_rejected' => (int) $item->quantity_rejected,
                    'sent_at' => $reception->sent_at?->toIso8601String(),
                    'validated_at' => $reception->validated_at?->toIso8601String(),
                ];
            })
            // Travelling shipments answer the question the vendor came with, so
            // they head the list whatever their age; closed ones stay below as
            // the history of what has already landed.
            ->sortByDesc('in_progress')
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
            'create' => $user->can('create', Product::class),
            'import' => $user->can('import', Product::class),
            'adjust' => $user->hasPermission(StockPermissions::ADJUST)
                || $user->hasPermission(StockPermissions::ADMIN_OVERRIDE),
            'block' => $user->hasPermission(StockPermissions::ADMIN_OVERRIDE),
            'audit' => $user->hasPermission(StockPermissions::ADMIN_OVERRIDE),
        ];
    }
}
