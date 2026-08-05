<?php

namespace App\Http\Controllers;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementSource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockAdjustment;
use App\Models\Store;
use App\Support\StockPermissions;
use Illuminate\Http\Request;
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
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission(StockPermissions::ADMIN_OVERRIDE), 403);

        $movements = StockAdjustment::acrossStores()
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
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('stock/movements/index', [
            'movements' => StockMovementResource::collection($movements)->response()->getData(true),
            'filters' => $request->only(['source', 'reason', 'store_id', 'product', 'from', 'to']),
            'sources' => StockMovementSource::options(),
            'reasons' => StockAdjustmentReason::auditOptions(),
            'stores' => Store::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ]);
    }
}
