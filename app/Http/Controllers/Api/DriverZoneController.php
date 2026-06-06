<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDriverSectorsRequest;
use App\Http\Resources\DriverResource;
use App\Http\Resources\SectorResource;
use App\Models\Sector;
use App\Models\User;
use App\Services\DriverZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DriverZoneController extends Controller
{
    public function __construct(private readonly DriverZoneService $driverZones) {}

    /**
     * GET /drivers/{driver}/sectors — sectors served by a driver.
     */
    public function index(User $driver): AnonymousResourceCollection
    {
        $this->ensureDriver($driver);

        $driver->load('sectors.city');

        return SectorResource::collection($driver->sectors);
    }

    /**
     * POST /drivers/{driver}/sectors — assign sectors to a driver.
     */
    public function store(AssignDriverSectorsRequest $request, User $driver): DriverResource
    {
        $this->ensureDriver($driver);

        $driver = $this->driverZones->assign(
            $driver,
            $request->sectorIds(),
            $request->boolean('replace')
        );

        return DriverResource::make($driver);
    }

    /**
     * DELETE /drivers/{driver}/sectors/{sector} — remove one sector.
     */
    public function destroy(User $driver, Sector $sector): JsonResponse
    {
        $this->ensureDriver($driver);

        $this->driverZones->remove($driver, $sector);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function ensureDriver(User $driver): void
    {
        abort_unless($driver->isDriver(), Response::HTTP_UNPROCESSABLE_ENTITY, 'The selected user is not a driver.');
    }
}
