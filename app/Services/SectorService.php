<?php

namespace App\Services;

use App\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SectorService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    /**
     * Build a filtered, searchable sector query (shared by web + API).
     */
    public function query(Request $request): Builder
    {
        return Sector::query()
            ->with('city')
            ->withCount(['orders', 'drivers'])
            ->when(
                $request->filled('search'),
                fn (Builder $q) => $q->where('name', 'like', '%'.$request->string('search').'%')
            )
            ->when(
                $request->filled('city_id'),
                fn (Builder $q) => $q->where('city_id', $request->integer('city_id'))
            )
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->where('is_active', $request->string('status') === 'active')
            )
            ->orderBy('name');
    }

    public function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PAGE_SIZE);

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PAGE_SIZE;
        }

        return min($perPage, self::MAX_PAGE_SIZE);
    }

    /**
     * Active sectors belonging to a city (used by the dependent dropdown).
     *
     * @return \Illuminate\Support\Collection<int, Sector>
     */
    public function activeForCity(int $cityId): \Illuminate\Support\Collection
    {
        return Sector::query()
            ->forCity($cityId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'city_id', 'name', 'delivery_price']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Sector
    {
        return Sector::create($data)->load('city');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Sector $sector, array $data): Sector
    {
        $sector->update($data);

        return $sector->refresh()->load('city');
    }

    public function delete(Sector $sector): void
    {
        $sector->delete();
    }
}
