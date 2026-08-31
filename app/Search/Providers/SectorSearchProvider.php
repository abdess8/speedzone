<?php

namespace App\Search\Providers;

use App\Models\Sector;
use App\Models\User;
use App\Search\SearchHit;

class SectorSearchProvider extends AbstractSearchProvider
{
    public function key(): string
    {
        return 'sectors';
    }

    public function label(): string
    {
        return __('search.objects.sectors');
    }

    public function icon(): string
    {
        return 'ri-road-map-line';
    }

    public function availableTo(User $user): bool
    {
        return $user->hasPermission('sectors.read');
    }

    public function search(User $user, string $term, int $limit): array
    {
        $sectors = Sector::query()
            ->with('city')
            ->where('name', 'like', $this->like($term))
            ->orderBy('name')
            ->limit($this->overfetch($limit))
            ->get();

        return collect($this->readable($sectors, $user, $limit))->map(fn (Sector $sector): SearchHit => new SearchHit(
            id: $sector->id,
            title: $sector->name,
            subtitle: $sector->city?->name,
            url: route('sectors.show', $sector),
            preview: [
                __('search.fields.city') => $sector->city?->name,
                __('search.fields.delivery_price') => $this->money($sector->delivery_price),
                __('search.fields.return_price') => $this->money($sector->return_price),
            ],
            badge: $sector->is_active ? __('common.active') : __('common.inactive'),
            badgeColor: $sector->is_active ? 'success' : 'secondary',
        ))->all();
    }
}
