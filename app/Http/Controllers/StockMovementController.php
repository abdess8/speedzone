<?php

namespace App\Http\Controllers;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementSource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockAdjustment;
use App\Models\Store;
use App\Support\SortableQuery;
use App\Support\StockPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The stock ledger, read whole.
 *
 * Gated on `stock.admin_override`: this is the cross-vendor audit view, and the
 * store boundary is deliberately lifted so a movement can be traced without
 * first knowing which shop it belonged to. A vendor reviews his own movements
 * from the product sheet instead, where the scope still applies.
 */
class StockMovementController extends Controller
{
    private const DEFAULT_SORT = 'created_at';

    /** Mirrors {@see User::getFullNameAttribute()} so the order matches the screen. */
    private const USER_FULL_NAME = "coalesce(nullif(concat_ws(' ', users.first_name, users.last_name), ''), users.name)";

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission(StockPermissions::ADMIN_OVERRIDE), 403);

        $query = StockAdjustment::acrossStores()
            ->with([
                'product:id,name,sku,store_id',
                'author:id,first_name,last_name,name',
                'reception:id,reference',
                'order:id,tracking_number',
                'store:id,name',
            ])
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->input('source')))
            ->when($request->filled('reason'), fn ($query) => $query->where('reason', $request->input('reason')))
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('product'), fn ($query) => $query->whereHas(
                'product',
                fn ($inner) => $inner->search($request->input('product'))
            ))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')));

        SortableQuery::apply($query, $request, self::sortable(), self::DEFAULT_SORT);

        $movements = $query->paginate(50)->withQueryString();

        return Inertia::render('stock/movements/index', [
            'movements' => StockMovementResource::collection($movements)->response()->getData(true),
            'filters' => array_merge(
                $request->only(['source', 'reason', 'store_id', 'product', 'from', 'to']),
                SortableQuery::state($request, self::sortable(), self::DEFAULT_SORT),
            ),
            'sources' => StockMovementSource::options(),
            'reasons' => StockAdjustmentReason::auditOptions(),
            'stores' => Store::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ]);
    }

    /**
     * Columns the ledger may be ordered on.
     *
     * The document column is left out: it is whichever of the two optional
     * references the line happens to carry, and ordering on "a slip number or
     * else a tracking number" ranks two unrelated series against each other.
     *
     * @return array<string, string|array<int, mixed>>
     */
    private static function sortable(): array
    {
        return [
            'created_at' => 'created_at',
            'product' => [
                DB::table('products')
                    ->select('name')
                    ->whereColumn('products.id', 'stock_adjustments.product_id'),
            ],
            'store' => [
                DB::table('stores')
                    ->select('name')
                    ->whereColumn('stores.id', 'stock_adjustments.store_id'),
            ],
            'source' => 'source',
            'reason' => 'reason',
            'stock_before' => 'stock_before',
            'delta' => 'delta',
            'stock_after' => 'stock_after',
            'author' => [
                DB::table('users')
                    ->select(DB::raw(self::USER_FULL_NAME))
                    ->whereColumn('users.id', 'stock_adjustments.user_id'),
            ],
        ];
    }
}
