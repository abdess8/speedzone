<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\City;
use App\Models\Store;
use App\Services\StoreService;
use App\Support\AdminSellerDirectory;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function __construct(private readonly StoreService $stores) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Store::class);

        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return $this->adminIndex($request);
        }

        $stores = Store::query()
            ->ownedBy($user->accountOwnerId())
            ->with('city')
            // Order counts must ignore the active-store boundary, otherwise
            // every row but the current one would report zero.
            ->withCount(['orders' => fn ($q) => $q->withoutGlobalScope('store')])
            ->when($user->isTeamMember(), fn ($q) => $q->whereIn('id', $user->accessibleStoreIds()))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Inertia::render('stores/index', [
            'stores' => StoreResource::collection($stores)->resolve($request),
            'can' => [
                'create' => $request->user()->can('create', Store::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Store::class);

        $admin = $request->user()->isSuperAdmin();
        $seller = $admin ? AdminSellerDirectory::find($request->integer('seller_id')) : null;

        return Inertia::render('stores/create', [
            'cities' => $this->cityOptions(),
            'hubCities' => City::hubOptions(),
            'admin' => $admin,
            'sellers' => $admin ? AdminSellerDirectory::options() : [],
            'seller' => $seller ? AdminSellerDirectory::present($seller) : null,
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Store::class);

        $store = $this->stores->create(
            AdminSellerDirectory::actingOwner($request),
            $request->safe()->except(['logo', 'seller_id']),
            $request->file('logo'),
        );

        return redirect()
            ->route('stores.index')
            ->with('success', __('stores.flash.created', ['name' => $store->name]));
    }

    public function edit(Request $request, Store $store): Response
    {
        $this->authorize('update', $store);

        $store->load('city', 'stockHubCity', 'owner:id,first_name,last_name,email');

        return Inertia::render('stores/edit', [
            'store' => StoreResource::make($store)->resolve($request),
            'cities' => $this->cityOptions(),
            'hubCities' => City::hubOptions(),
            'admin' => $request->user()->isSuperAdmin(),
            'seller' => $store->owner ? AdminSellerDirectory::present($store->owner) : null,
            'can' => [
                'delete' => $request->user()->can('delete', $store) && $this->stores->canDelete($store),
            ],
        ]);
    }

    public function update(StoreRequest $request, Store $store): RedirectResponse
    {
        $this->authorize('update', $store);

        $this->stores->update($store, $request->safe()->except(['logo', 'seller_id']), $request->file('logo'));

        return redirect()
            ->route('stores.index')
            ->with('success', __('stores.flash.updated', ['name' => $store->name]));
    }

    public function destroy(Request $request, Store $store): RedirectResponse
    {
        $this->authorize('delete', $store);

        if (! $this->stores->canDelete($store)) {
            return back()->with('error', __('stores.flash.cannot_delete'));
        }

        $name = $store->name;
        $this->stores->delete($store);

        return redirect()
            ->route('stores.index')
            ->with('success', __('stores.flash.deleted', ['name' => $name]));
    }

    private function adminIndex(Request $request): Response
    {
        $search = (string) $request->string('search');
        $sortable = [
            'name' => 'name',
            'seller' => 'owner_id',
            'status' => 'is_active',
            'created_at' => 'created_at',
        ];

        $query = Store::query()
            ->with(['city:id,name', 'owner:id,first_name,last_name,email'])
            ->withCount(['orders' => fn ($q) => $q->withoutGlobalScope('store')])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('category', 'like', '%'.$search.'%')
                        ->orWhereHas('owner', function ($seller) use ($search) {
                            $seller->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                $request->integer('seller_id') > 0,
                fn ($query) => $query->where('owner_id', $request->integer('seller_id'))
            )
            ->when($request->string('status')->toString() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->string('status')->toString() === 'inactive', fn ($query) => $query->where('is_active', false));

        SortableQuery::apply($query, $request, $sortable, 'created_at');

        $stores = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Store $store) => StoreResource::make($store)->resolve($request));

        return Inertia::render('stores/admin', [
            'stores' => $stores,
            'stats' => [
                'total' => Store::query()->count(),
                'sellers' => Store::query()->distinct()->count('owner_id'),
                'inactive' => Store::query()->where('is_active', false)->count(),
            ],
            'sellers' => AdminSellerDirectory::options(),
            'filters' => array_merge(
                $request->only(['search', 'seller_id', 'status']),
                SortableQuery::state($request, $sortable, 'created_at')
            ),
            'can' => [
                'create' => true,
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cityOptions(): array
    {
        return City::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (City $city) => ['id' => $city->id, 'name' => $city->name])
            ->all();
    }
}
