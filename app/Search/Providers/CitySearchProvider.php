<?php

namespace App\Search\Providers;

use App\Models\City;
use App\Models\User;
use App\Search\SearchHit;

class CitySearchProvider extends AbstractSearchProvider
{
    public function key(): string
    {
        return 'cities';
    }

    public function label(): string
    {
        return __('search.objects.cities');
    }

    public function icon(): string
    {
        return 'ri-map-pin-line';
    }

    public function availableTo(User $user): bool
    {
        return $user->hasPermission('cities.read');
    }

    public function search(User $user, string $term, int $limit): array
    {
        $cities = City::query()
            ->withCount('sectors')
            ->where('name', 'like', $this->like($term))
            ->orderBy('name')
            ->limit($this->overfetch($limit))
            ->get();

        return collect($this->readable($cities, $user, $limit))->map(fn (City $city): SearchHit => new SearchHit(
            id: $city->id,
            title: $city->name,
            subtitle: $city->region,
            url: route('cities.show', $city),
            preview: [
                __('search.fields.code') => $city->code,
                __('search.fields.region') => $city->region,
                __('search.fields.sectors_count') => (string) $city->sectors_count,
                __('search.fields.stock_hub') => $city->is_stock_hub ? __('common.yes') : __('common.no'),
            ],
            badge: $city->is_active ? __('common.active') : __('common.inactive'),
            badgeColor: $city->is_active ? 'success' : 'secondary',
        ))->all();
    }
}
