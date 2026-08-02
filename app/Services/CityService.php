<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CityService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    /**
     * Build a filtered, searchable city query (shared by web + API).
     */
    public function query(Request $request): Builder
    {
        return City::query()
            ->withCount(['sectors', 'activeSectors'])
            ->when(
                $request->filled('search'),
                fn (Builder $q) => $q->where(function (Builder $sub) use ($request) {
                    $term = '%'.$request->string('search').'%';
                    $sub->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('region', 'like', $term);
                })
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
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): City
    {
        return City::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(City $city, array $data): City
    {
        $city->update($data);

        return $city->refresh();
    }

    /**
     * A city cannot be removed while it still has active sectors attached.
     */
    public function canDelete(City $city): bool
    {
        return ! $city->sectors()->where('is_active', true)->exists();
    }

    public function delete(City $city): void
    {
        $city->delete();
    }
}
