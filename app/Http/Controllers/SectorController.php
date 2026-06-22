<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSectorRequest;
use App\Http\Requests\UpdateSectorRequest;
use App\Http\Resources\SectorResource;
use App\Models\City;
use App\Models\Sector;
use App\Services\SectorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectorController extends Controller
{
    public function __construct(private readonly SectorService $sectors) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sector::class);

        $sectors = $this->sectors->query($request)
            ->paginate($this->sectors->perPage($request))
            ->withQueryString();

        return Inertia::render('sectors/index', [
            'sectors' => SectorResource::collection($sectors)->response()->getData(true),
            'filters' => $request->only(['search', 'city_id', 'status', 'per_page']),
            'cities' => $this->cityOptions(),
            'can' => $this->abilities($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Sector::class);

        return Inertia::render('sectors/create', [
            'cities' => $this->cityOptions(),
            'defaultCityId' => $request->integer('city_id') ?: null,
        ]);
    }

    public function store(StoreSectorRequest $request): RedirectResponse
    {
        $this->authorize('create', Sector::class);

        $sector = $this->sectors->create($request->validated());

        return redirect()
            ->route('sectors.show', $sector)
            ->with('success', "Sector {$sector->name} created successfully.");
    }

    public function show(Request $request, Sector $sector): Response
    {
        $this->authorize('view', $sector);

        $sector->load([
            'city',
            'drivers' => fn ($q) => $q->orderBy('first_name')->orderBy('name'),
        ])->loadCount(['orders', 'drivers']);

        return Inertia::render('sectors/show', [
            'sector' => SectorResource::make($sector)->resolve($request),
            'can' => $this->abilities($request),
        ]);
    }

    public function edit(Request $request, Sector $sector): Response
    {
        $this->authorize('update', $sector);

        $sector->load('city');

        return Inertia::render('sectors/edit', [
            'sector' => SectorResource::make($sector)->resolve($request),
            'cities' => $this->cityOptions(),
        ]);
    }

    public function update(UpdateSectorRequest $request, Sector $sector): RedirectResponse
    {
        $this->authorize('update', $sector);

        $this->sectors->update($sector, $request->validated());

        return redirect()
            ->route('sectors.show', $sector)
            ->with('success', 'Sector updated successfully.');
    }

    public function destroy(Request $request, Sector $sector): RedirectResponse
    {
        $this->authorize('delete', $sector);

        $cityId = $sector->city_id;
        $this->sectors->delete($sector);

        if ($request->input('return_to') === 'city' && $cityId) {
            return redirect()
                ->route('cities.show', $cityId)
                ->with('success', 'Sector deleted successfully.');
        }

        return redirect()
            ->route('sectors.index')
            ->with('success', 'Sector deleted successfully.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cityOptions(): array
    {
        return City::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'region'])
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
                'region' => $city->region,
            ])
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'create' => $user->can('create', Sector::class),
            'update' => $user->hasPermission('sectors.update'),
            'delete' => $user->hasPermission('sectors.delete'),
        ];
    }
}
