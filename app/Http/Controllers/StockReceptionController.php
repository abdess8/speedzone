<?php

namespace App\Http\Controllers;

use App\Enums\StockReceptionStatus;
use App\Http\Requests\CollectStockReceptionRequest;
use App\Http\Requests\StoreStockReceptionRequest;
use App\Http\Requests\ValidateStockReceptionRequest;
use App\Http\Resources\StockReceptionListResource;
use App\Http\Resources\StockReceptionStatusHistoryResource;
use App\Models\City;
use App\Models\Product;
use App\Models\StockReception;
use App\Models\Store;
use App\Models\User;
use App\Services\StockReceptionService;
use App\Support\SortableQuery;
use App\Support\StockPermissions;
use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inbound shipments, from all three sides of the counter.
 *
 * The same screens serve the vendor who declares a parcel, the collector who goes
 * to fetch it and the hub agent who counts it in. Which half is editable is decided
 * by the policy, not by a separate controller, so the three views can never
 * disagree about the document.
 */
class StockReceptionController extends Controller
{
    /**
     * Newest slip first, as before the table became sortable. The key is not one
     * of the visible columns, so no header claims it.
     */
    private const DEFAULT_SORT = 'id';

    /** Mirrors {@see User::getFullNameAttribute()} so the order matches the screen. */
    private const USER_FULL_NAME = "coalesce(nullif(concat_ws(' ', users.first_name, users.last_name), ''), users.name)";

    public function __construct(
        private readonly StockReceptionService $receptionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockReception::class);

        $user = $request->user();
        $isCollector = $this->isFieldCollector($user);

        $sort = SortableQuery::state($request, self::sortable(), self::DEFAULT_SORT);

        // A reader who has picked a column is no longer reading a worklist, so
        // the two queue buckets below only float what is waiting on him while he
        // has not: floating them anyway would sort within the buckets and look
        // like the click did nothing. The default key is not one of the headers,
        // so it can only still be in force if nobody clicked one.
        $isWorklist = $sort['sort'] === self::DEFAULT_SORT;

        $query = StockReception::query()
            ->with([
                'sender:id,first_name,last_name,name',
                'collector:id,first_name,last_name,name',
                'receiver:id,first_name,last_name,name',
                'seller:id,first_name,last_name,name',
                'destinationCity:id,name',
                'store:id,name,city_id',
                'store.city:id,name',
            ])
            ->withCount('items')
            ->withSum('items', 'quantity_sent')
            ->withSum('items', 'quantity_collected')
            ->withSum('items', 'quantity_received')
            // A collector is shown his own round: the shops he can drive to, plus
            // whatever he is still carrying. The national backlog is not his queue.
            ->when($isCollector, fn (Builder $query) => $query->where(
                fn (Builder $mine) => $mine
                    ->forPickupCities($user->cityIds())
                    ->orWhere('collected_by', $user->id)
            ))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->input('status')))
            ->when($request->filled('reference'), fn (Builder $query) => $query->where('reference', 'like', '%'.$request->input('reference').'%'))
            ->when(
                $request->filled('destination_city_id'),
                fn (Builder $query) => $query->forDestinationCity($request->integer('destination_city_id'))
            )
            // Both staff screens open on a queue somebody has to work, so the
            // shipments waiting on the reader come first: parcels to fetch for a
            // collector, parcels to count for the depot.
            ->when($isWorklist && $isCollector, fn (Builder $query) => $query->orderByRaw(
                'CASE WHEN status = ? THEN 0 ELSE 1 END',
                [StockReceptionStatus::AWAITING_PICKUP->value]
            ))
            ->when(
                $isWorklist && $user->hasPermission(StockPermissions::RECEIVE_INBOUND),
                fn (Builder $query) => $query->orderByRaw(
                    'CASE WHEN status = ? THEN 0 ELSE 1 END',
                    [StockReceptionStatus::IN_TRANSIT->value]
                )
            );

        SortableQuery::apply($query, $request, self::sortable(), self::DEFAULT_SORT);

        $receptions = $query->paginate(25)->withQueryString();

        return Inertia::render('stock/receptions/index', [
            'receptions' => StockReceptionListResource::collection($receptions)->response()->getData(true),
            'filters' => array_merge($request->only(['status', 'reference', 'destination_city_id']), $sort),
            'statuses' => StockReceptionStatus::options(),
            'hubCities' => City::hubOptions(),
            'can' => [
                'create' => $user->can('create', StockReception::class),
                'collect' => $user->hasPermission(StockPermissions::COLLECT_INBOUND),
                'receive' => $user->hasPermission(StockPermissions::RECEIVE_INBOUND),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', StockReception::class);

        return Inertia::render('stock/receptions/create', [
            'products' => $this->productOptions(),
            'hubCities' => City::hubOptions(),
            'shopDepotCityId' => $this->shopDepotCityId(),
        ]);
    }

    public function store(StoreStockReceptionRequest $request): RedirectResponse
    {
        $this->authorize('create', StockReception::class);

        $reception = $this->receptionService->create($request->validated(), $request->user());

        return redirect()
            ->route('stock-receptions.show', $reception)
            ->with('success', __('stock.receptions.flash.created', ['reference' => $reception->reference]));
    }

    public function show(Request $request, StockReception $reception): Response
    {
        $this->authorize('view', $reception);

        $reception->load([
            'items.product',
            'sender',
            'collector',
            'receiver',
            'seller',
            'destinationCity',
            'store.city',
            'statusHistories.changedBy',
        ]);

        return Inertia::render('stock/receptions/show', [
            'reception' => $this->detailRow($reception),
            'can' => [
                'update' => $request->user()->can('update', $reception),
                'send' => $request->user()->can('send', $reception),
                'collect' => $request->user()->can('collect', $reception),
                'dispatch' => $request->user()->can('dispatch', $reception),
                'receive' => $request->user()->can('receive', $reception),
                'cancel' => $request->user()->can('cancel', $reception),
            ],
        ]);
    }

    public function edit(Request $request, StockReception $reception): Response
    {
        $this->authorize('update', $reception);

        $reception->load('items.product', 'destinationCity', 'store.city', 'statusHistories.changedBy');

        return Inertia::render('stock/receptions/edit', [
            'reception' => $this->detailRow($reception),
            'products' => $this->productOptions(),
            'hubCities' => City::hubOptions(),
            'shopDepotCityId' => $this->shopDepotCityId(),
        ]);
    }

    public function update(StoreStockReceptionRequest $request, StockReception $reception): RedirectResponse
    {
        $this->authorize('update', $reception);

        $this->receptionService->update($reception, $request->validated(), $request->user());

        return redirect()
            ->route('stock-receptions.show', $reception)
            ->with('success', __('stock.receptions.flash.updated'));
    }

    /**
     * Ask us to come for the parcel: the declaration is frozen from here on.
     */
    public function send(Request $request, StockReception $reception): RedirectResponse
    {
        $this->authorize('send', $reception);

        $this->receptionService->markAsSent($reception, $request->user());

        return redirect()
            ->route('stock-receptions.show', $reception)
            ->with('success', __('stock.receptions.flash.sent'));
    }

    /**
     * The collector signs for what the shop handed over.
     */
    public function collect(CollectStockReceptionRequest $request, StockReception $reception): RedirectResponse
    {
        $this->authorize('collect', $reception);

        $this->receptionService->collect(
            reception: $reception,
            lines: $request->lines(),
            actor: $request->user(),
            collectionNotes: $request->input('collection_notes'),
        );

        return redirect()
            ->route('stock-receptions.show', $reception)
            ->with('success', __('stock.receptions.flash.collected', ['reference' => $reception->reference]));
    }

    /**
     * The goods leave for the depot.
     */
    public function dispatchToDepot(Request $request, StockReception $reception): RedirectResponse
    {
        $this->authorize('dispatch', $reception);

        $this->receptionService->dispatchToDepot($reception, $request->user());

        return redirect()
            ->route('stock-receptions.show', $reception)
            ->with('success', __('stock.receptions.flash.dispatched'));
    }

    /**
     * Close the shipment at the depot and credit what was counted.
     */
    public function validateReception(ValidateStockReceptionRequest $request, StockReception $reception): RedirectResponse
    {
        $this->authorize('receive', $reception);

        $this->receptionService->validate(
            reception: $reception,
            lines: $request->lines(),
            actor: $request->user(),
            receptionNotes: $request->input('reception_notes'),
            receivedAt: $request->input('received_at'),
        );

        return redirect()
            ->route('stock-receptions.show', $reception)
            ->with('success', __('stock.receptions.flash.validated', ['reference' => $reception->reference]));
    }

    public function cancel(Request $request, StockReception $reception): RedirectResponse
    {
        $this->authorize('cancel', $reception);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->receptionService->cancel($reception, $data['reason'] ?? null, $request->user());

        return redirect()
            ->route('stock-receptions.show', $reception)
            ->with('success', __('stock.receptions.flash.cancelled'));
    }

    /**
     * Columns the shipment list may be ordered on.
     *
     * The three quantity columns and the line count are the aggregates the query
     * already selects, so ordering names the alias rather than summing twice.
     * Everything borrowed from another table is read through a correlated
     * subquery: the filters above name `status` unqualified, and both `users`
     * and `stores` carry a column of that name.
     *
     * @return array<string, string|array<int, mixed>>
     */
    private static function sortable(): array
    {
        return [
            'id' => 'id',
            'reference' => 'reference',
            'status' => 'status',
            'seller' => [
                DB::table('users')
                    ->select(DB::raw(self::USER_FULL_NAME))
                    ->whereColumn('users.id', 'stock_receptions.seller_id'),
            ],
            'pickup_city' => [
                DB::table('stores')
                    ->join('cities', 'cities.id', '=', 'stores.city_id')
                    ->select('cities.name')
                    ->whereColumn('stores.id', 'stock_receptions.store_id'),
            ],
            'destination_city' => [
                DB::table('cities')
                    ->select('name')
                    ->whereColumn('cities.id', 'stock_receptions.destination_city_id'),
            ],
            'items_count' => 'items_count',
            'quantity_sent' => 'items_sum_quantity_sent',
            'quantity_collected' => 'items_sum_quantity_collected',
            'quantity_received' => 'items_sum_quantity_received',
            'sent_at' => 'sent_at',
            'received_at' => 'received_at',
        ];
    }

    /**
     * Whether this reader is out in the field rather than behind a depot counter.
     *
     * Somebody holding both grants is treated as depot staff: the wider view is
     * the one that lets him unblock a shipment, and the narrow queue would hide
     * exactly the documents he is there to sort out.
     */
    private function isFieldCollector(?User $user): bool
    {
        return $user !== null
            && $user->hasPermission(StockPermissions::COLLECT_INBOUND)
            && ! $user->hasPermission(StockPermissions::RECEIVE_INBOUND)
            && ! $user->hasPermission(StockPermissions::ADMIN_OVERRIDE);
    }

    /**
     * The depot this shop already warehouses in, if any.
     *
     * Sent to the form so the destination is pre-filled and locked: a shop keeps
     * its whole catalog in one city, and the second shipment has nothing left to
     * decide.
     */
    private function shopDepotCityId(): ?int
    {
        $storeId = app(StoreContext::class)->id();

        return $storeId === null
            ? null
            : Store::query()->whereKey($storeId)->value('stock_hub_city_id');
    }

    /**
     * Catalog references a shipment can be built from.
     *
     * Out-of-stock products are included and not flagged: sending stock in is
     * precisely what a vendor does about an empty shelf.
     *
     * @return array<int, array<string, mixed>>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->active()
            ->orderBy('name')
            ->limit((int) config('stock.picklist_limit', 2000))
            ->get(['id', 'store_id', 'name', 'sku', 'barcode', 'category', 'unit_price', 'stock_quantity', 'is_fragile', 'photo_path', 'blocked_at'])
            ->map(fn (Product $product) => $product->toPickOption())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRow(StockReception $reception): array
    {
        $status = $reception->statusEnum();

        return [
            'id' => $reception->id,
            'reference' => $reception->reference,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'status_icon' => $status->icon(),
            'destination_city_id' => $reception->destination_city_id,
            'destination_city' => $reception->destinationCity?->name,
            'pickup_city' => $reception->store?->city?->name ?? $reception->seller?->city?->name,
            'shop' => $reception->store?->name,
            'sent_at' => $reception->sent_at?->toDateString(),
            'collected_at' => $reception->collected_at?->toIso8601String(),
            'dispatched_at' => $reception->dispatched_at?->toIso8601String(),
            'received_at' => $reception->received_at?->toDateString(),
            'validated_at' => $reception->validated_at?->toIso8601String(),
            'sending_notes' => $reception->sending_notes,
            'collection_notes' => $reception->collection_notes,
            'reception_notes' => $reception->reception_notes,
            'seller' => $reception->seller?->full_name,
            'sender' => $reception->sender?->full_name,
            'collector' => $reception->collector?->full_name,
            'receiver' => $reception->receiver?->full_name,
            'totals' => [
                'sent' => $reception->totalSent(),
                'collected' => $reception->totalCollected(),
                'received' => $reception->totalReceived(),
                'rejected' => $reception->totalRejected(),
                'unaccounted' => $reception->unaccountedUnits(),
            ],
            'items' => $reception->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product?->name,
                'sku' => $item->product?->sku,
                'barcode' => $item->product?->barcode,
                'photo_url' => $item->product?->photo_url,
                'initials' => $item->product?->initials,
                'stock_quantity' => (int) ($item->product?->stock_quantity ?? 0),
                'quantity_sent' => (int) $item->quantity_sent,
                'quantity_collected' => $item->quantity_collected,
                'quantity_received' => $item->quantity_received,
                'quantity_rejected' => $item->quantity_rejected,
                'baseline_quantity' => $item->baselineQuantity(),
                'collection_gap' => $item->collectionGap(),
                'discrepancy' => $item->discrepancy(),
                'note' => $item->note,
            ])->all(),
            'status_history' => StockReceptionStatusHistoryResource::collection(
                $reception->statusHistories
            )->resolve(),
        ];
    }
}
