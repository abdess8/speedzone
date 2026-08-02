<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Http\Resources\SectorResource;
use App\Models\City;
use App\Services\CityService;
use App\Services\SectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CityController extends Controller
{
    public function __construct(private readonly CityService $cities) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $cities = $this->cities->query($request)
            ->paginate($this->cities->perPage($request))
            ->withQueryString();

        return CityResource::collection($cities);
    }

    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = $this->cities->create($request->validated());

        return CityResource::make($city)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(City $city): CityResource
    {
        $city->loadCount(['sectors', 'activeSectors']);

        return CityResource::make($city);
    }

    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $this->cities->update($city, $request->validated());

        return CityResource::make($city);
    }

    public function destroy(City $city): JsonResponse
    {
        abort_unless(
            $this->cities->canDelete($city),
            Response::HTTP_CONFLICT,
            'Cannot delete a city that still has active sectors.'
        );

        $this->cities->delete($city);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * GET /cities/{city}/sectors — active sectors for the dependent dropdown.
     */
    public function sectors(City $city, SectorService $sectors): AnonymousResourceCollection
    {
        return SectorResource::collection($sectors->activeForCity($city->id));
    }
}
