<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Http\Resources\SectorResource;
use App\Models\City;
use App\Services\CityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function __construct(private readonly CityService $cities) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', City::class);

        $cities = $this->cities->query($request)
            ->paginate($this->cities->perPage($request))
            ->withQueryString();

        return Inertia::render('cities/index', [
            'cities' => CityResource::collection($cities)->response()->getData(true),
            'filters' => $request->only(['search', 'status', 'per_page']),
            'can' => $this->abilities($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', City::class);

        return Inertia::render('cities/create');
    }

    public function store(StoreCityRequest $request): RedirectResponse
    {
        $this->authorize('create', City::class);

        $city = $this->cities->create($request->validated());

        return redirect()
            ->route('cities.show', $city)
            ->with('success', "City {$city->name} created successfully.");
    }

    public function show(Request $request, City $city): Response
    {
        $this->authorize('view', $city);

        $city->loadCount(['sectors', 'activeSectors']);
        $city->load(['sectors' => fn ($q) => $q->withCount('orders')->orderBy('name')]);

        return Inertia::render('cities/show', [
            'city' => CityResource::make($city)->resolve($request),
            'can' => $this->abilities($request, includeSectors: true),
        ]);
    }

    public function edit(Request $request, City $city): Response
    {
        $this->authorize('update', $city);

        return Inertia::render('cities/edit', [
            'city' => CityResource::make($city)->resolve($request),
        ]);
    }

    public function update(UpdateCityRequest $request, City $city): RedirectResponse
    {
        $this->authorize('update', $city);

        $this->cities->update($city, $request->validated());

        return redirect()
            ->route('cities.show', $city)
            ->with('success', 'City updated successfully.');
    }

    public function destroy(Request $request, City $city): RedirectResponse
    {
        $this->authorize('delete', $city);

        if (! $this->cities->canDelete($city)) {
            return back()->with('error', 'This city still has active sectors and cannot be deleted.');
        }

        $this->cities->delete($city);

        return redirect()
            ->route('cities.index')
            ->with('success', 'City deleted successfully.');
    }

    /**
     * JSON list of a city's active sectors — powers the dependent dropdown
     * on the order form (same-session, no token required).
     */
    public function sectors(Request $request, City $city, \App\Services\SectorService $sectors): JsonResponse
    {
        return response()->json([
            'data' => SectorResource::collection($sectors->activeForCity($city->id))->resolve($request),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Request $request, bool $includeSectors = false): array
    {
        $user = $request->user();

        $abilities = [
            'create' => $user->can('create', City::class),
            'update' => $user->hasPermission('cities.update'),
            'delete' => $user->hasPermission('cities.delete'),
        ];

        if ($includeSectors) {
            $abilities['sectors_create'] = $user->hasPermission('sectors.create');
            $abilities['sectors_update'] = $user->hasPermission('sectors.update');
            $abilities['sectors_delete'] = $user->hasPermission('sectors.delete');
        }

        return $abilities;
    }
}
