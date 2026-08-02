<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignDriverSectorsRequest;
use App\Http\Resources\DriverResource;
use App\Models\City;
use App\Models\Sector;
use App\Models\User;
use App\Services\DriverZoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverZoneController extends Controller
{
    public function __construct(private readonly DriverZoneService $driverZones) {}

    public function index(Request $request): Response
    {
        $this->authorize('driver_zones.read');

        $drivers = $this->driverZones->query($request)
            ->paginate($this->driverZones->perPage($request))
            ->withQueryString();

        return Inertia::render('driver-zones/index', [
            'drivers' => DriverResource::collection($drivers)->response()->getData(true),
            'filters' => $request->only(['search', 'city_id', 'sector_id', 'per_page']),
            'cities' => $this->cityOptions(),
            'sectors' => $this->sectorOptions(),
            'can' => [
                'assign' => $request->user()->can('driver_zones.assign'),
                'remove' => $request->user()->can('driver_zones.remove'),
            ],
        ]);
    }

    public function assign(AssignDriverSectorsRequest $request, User $driver): RedirectResponse
    {
        $this->authorize('driver_zones.assign');

        $this->ensureDriverRole($driver);

        $this->driverZones->assign(
            $driver,
            $request->sectorIds(),
            $request->boolean('replace')
        );

        return back()->with('success', 'Sectors assigned successfully.');
    }

    public function remove(Request $request, User $driver, Sector $sector): RedirectResponse
    {
        $this->authorize('driver_zones.remove');

        $this->ensureDriverRole($driver);

        $this->driverZones->remove($driver, $sector);

        return back()->with('success', 'Sector removed from driver.');
    }

    private function ensureDriverRole(User $driver): void
    {
        abort_unless($driver->isDriver(), 422, 'The selected user is not a driver.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cityOptions(): array
    {
        return City::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (City $city) => ['id' => $city->id, 'name' => $city->name])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sectorOptions(): array
    {
        return Sector::query()
            ->active()
            ->with('city:id,name')
            ->orderBy('name')
            ->get(['id', 'city_id', 'name', 'delivery_price'])
            ->map(fn (Sector $sector) => [
                'id' => $sector->id,
                'city_id' => $sector->city_id,
                'city_name' => $sector->city?->name,
                'name' => $sector->name,
                'delivery_price' => (float) $sector->delivery_price,
            ])
            ->all();
    }
}
