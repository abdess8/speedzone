<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\City;
use App\Models\Store;
use App\Services\StoreService;
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

        return Inertia::render('stores/create', [
            'cities' => $this->cityOptions(),
            'hubCities' => City::hubOptions(),
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Store::class);

        $store = $this->stores->create(
            $request->user(),
            $request->safe()->except('logo'),
            $request->file('logo'),
        );

        return redirect()
            ->route('stores.index')
            ->with('success', __('stores.flash.created', ['name' => $store->name]));
    }

    public function edit(Request $request, Store $store): Response
    {
        $this->authorize('update', $store);

        $store->load('city', 'stockHubCity');

        return Inertia::render('stores/edit', [
            'store' => StoreResource::make($store)->resolve($request),
            'cities' => $this->cityOptions(),
            'hubCities' => City::hubOptions(),
            'can' => [
                'delete' => $request->user()->can('delete', $store) && $this->stores->canDelete($store),
            ],
        ]);
    }

    public function update(StoreRequest $request, Store $store): RedirectResponse
    {
        $this->authorize('update', $store);

        $this->stores->update($store, $request->safe()->except('logo'), $request->file('logo'));

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
