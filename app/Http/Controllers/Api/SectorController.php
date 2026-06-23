<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSectorRequest;
use App\Http\Requests\UpdateSectorRequest;
use App\Http\Resources\SectorResource;
use App\Models\Sector;
use App\Services\SectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SectorController extends Controller
{
    public function __construct(private readonly SectorService $sectors) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $sectors = $this->sectors->query($request)
            ->paginate($this->sectors->perPage($request))
            ->withQueryString();

        return SectorResource::collection($sectors);
    }

    public function store(StoreSectorRequest $request): JsonResponse
    {
        $sector = $this->sectors->create($request->validated());

        return SectorResource::make($sector)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Sector $sector): SectorResource
    {
        $sector->load('city')->loadCount(['orders', 'drivers']);

        return SectorResource::make($sector);
    }

    public function update(UpdateSectorRequest $request, Sector $sector): SectorResource
    {
        $this->sectors->update($sector, $request->validated());

        return SectorResource::make($sector);
    }

    public function destroy(Sector $sector): JsonResponse
    {
        $this->sectors->delete($sector);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
