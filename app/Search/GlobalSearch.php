<?php

namespace App\Search;

use App\Models\User;
use App\Search\Providers\CitySearchProvider;
use App\Search\Providers\InventorySearchProvider;
use App\Search\Providers\InvoiceSearchProvider;
use App\Search\Providers\OrderSearchProvider;
use App\Search\Providers\PickupRequestSearchProvider;
use App\Search\Providers\ProductSearchProvider;
use App\Search\Providers\ReturnSearchProvider;
use App\Search\Providers\SectorSearchProvider;
use App\Search\Providers\StockReceptionSearchProvider;
use App\Search\Providers\TransferSearchProvider;
use App\Search\Providers\UserSearchProvider;

/**
 * The global search bar, one object at a time or all of them at once.
 *
 * Searching everything means one query per object, so the per-object result
 * count is deliberately small: the bar is a way to *jump* to a record, and
 * anyone who needs to see more than a handful is better served by the list
 * screen the provider points at. Narrowing the scope to a single object spends
 * that same budget on depth instead.
 */
class GlobalSearch
{
    /** Rows per object when every object is searched at once. */
    private const LIMIT_PER_OBJECT = 5;

    /** Rows when the search is narrowed to a single object. */
    private const LIMIT_SINGLE_OBJECT = 15;

    /** Below this a term matches half the database and costs a full scan. */
    public const MIN_TERM_LENGTH = 2;

    /**
     * Registration order, which is also the order the groups appear in.
     *
     * @var array<int, class-string<SearchProvider>>
     */
    private const PROVIDERS = [
        OrderSearchProvider::class,
        ReturnSearchProvider::class,
        PickupRequestSearchProvider::class,
        TransferSearchProvider::class,
        InvoiceSearchProvider::class,
        ProductSearchProvider::class,
        InventorySearchProvider::class,
        StockReceptionSearchProvider::class,
        UserSearchProvider::class,
        CitySearchProvider::class,
        SectorSearchProvider::class,
    ];

    /**
     * The objects this user may search, in display order.
     *
     * @return array<int, SearchProvider>
     */
    public function providersFor(User $user): array
    {
        return array_values(array_filter(
            array_map(fn (string $provider) => app($provider), self::PROVIDERS),
            fn (SearchProvider $provider) => $provider->availableTo($user)
        ));
    }

    /**
     * The scope picker: "All", then one entry per searchable object.
     *
     * @return array<int, array<string, string>>
     */
    public function scopesFor(User $user): array
    {
        return array_map(fn (SearchProvider $provider) => [
            'key' => $provider->key(),
            'label' => $provider->label(),
            'icon' => $provider->icon(),
        ], $this->providersFor($user));
    }

    /**
     * @param  string|null  $scope  A provider key, or null for every object.
     * @return array<int, array<string, mixed>> Groups holding at least one hit.
     */
    public function search(User $user, string $term, ?string $scope = null): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        $providers = $this->providersFor($user);

        if ($scope !== null && $scope !== 'all') {
            $providers = array_values(array_filter(
                $providers,
                fn (SearchProvider $provider) => $provider->key() === $scope
            ));
        }

        $limit = count($providers) === 1 ? self::LIMIT_SINGLE_OBJECT : self::LIMIT_PER_OBJECT;
        $groups = [];

        foreach ($providers as $provider) {
            $hits = $provider->search($user, $term, $limit);

            if ($hits === []) {
                continue;
            }

            $groups[] = [
                'key' => $provider->key(),
                'label' => $provider->label(),
                'icon' => $provider->icon(),
                'hits' => array_map(fn (SearchHit $hit) => $hit->toArray(), $hits),
            ];
        }

        return $groups;
    }
}
