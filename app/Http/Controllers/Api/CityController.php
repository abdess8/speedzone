<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $cities = City::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%'))
            ->when($request->boolean('active_only', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return CityResource::collection($cities);
    }

    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = City::create($request->validated());

        return CityResource::make($city)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(City $city): CityResource
    {
        return CityResource::make($city);
    }

    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $city->update($request->validated());

        return CityResource::make($city);
    }

    public function destroy(City $city): JsonResponse
    {
        abort_if($city->orders()->exists(), Response::HTTP_CONFLICT, 'Cannot delete a city that still has orders.');

        $city->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
