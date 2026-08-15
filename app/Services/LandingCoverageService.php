<?php

namespace App\Services;

use App\Support\MoroccoCityMap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Coverage + price grid published on the public landing page.
 *
 * Both the map and the tariff table are derived from the live sector prices
 * so the marketing site can never advertise a rate the dispatch side no longer
 * applies. Cities are collapsed onto a single entry ("CASABLANCA VILLE" and
 * "CASABLANCA REGION" become one "Casablanca") because a visitor thinks in
 * cities, not in the internal ville/région split.
 */
final class LandingCoverageService
{
    private const CACHE_KEY = 'landing.coverage.payload';

    private const CACHE_TTL = 1800;

    /**
     * @return array{
     *     cities: array<int, array<string, mixed>>,
     *     regions: array<int, string>,
     *     totals: array{cities: int, sectors: int, regions: int},
     *     price: array{min: float, max: float}
     * }
     */
    public static function payload(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::build());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(): array
    {
        $rows = DB::table('sectors')
            ->join('cities', 'cities.id', '=', 'sectors.city_id')
            ->whereNull('sectors.deleted_at')
            ->whereNull('cities.deleted_at')
            ->where('sectors.is_active', true)
            ->where('cities.is_active', true)
            ->get([
                'cities.name as city',
                'cities.region as region',
                'sectors.delivery_price as price',
                'sectors.delivery_delay as delay',
            ]);

        $grouped = [];

        foreach ($rows as $row) {
            $key = MoroccoCityMap::normalise((string) $row->city);

            if ($key === '') {
                continue;
            }

            $price = (float) $row->price;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'name' => MoroccoCityMap::label($key),
                    'region' => MoroccoCityMap::region((string) $row->region),
                    'region_key' => MoroccoCityMap::regionKey((string) $row->region),
                    'min' => $price,
                    'max' => $price,
                    'delay' => (string) $row->delay,
                    'sectors' => 0,
                ];
            }

            $grouped[$key]['sectors']++;
            $grouped[$key]['max'] = max($grouped[$key]['max'], $price);

            // The headline price is the cheapest sector of the city, so the
            // advertised delay has to be the one that actually comes with it.
            $cheaper = $price < $grouped[$key]['min'];
            $sameButFaster = $price === $grouped[$key]['min'] && (string) $row->delay < $grouped[$key]['delay'];

            if ($cheaper || $sameButFaster) {
                $grouped[$key]['min'] = $price;
                $grouped[$key]['delay'] = (string) $row->delay;
            }
        }

        $grouped = self::withInheritedCities($grouped);

        $cities = [];

        foreach ($grouped as $key => $city) {
            $place = MoroccoCityMap::place($key);

            $cities[] = [
                'key' => $key,
                'name' => $city['name'],
                'region' => $city['region'],
                'region_key' => $city['region_key'],
                'price' => round($city['min'], 2),
                'price_max' => round($city['max'], 2),
                'delay' => $city['delay'],
                'sectors' => $city['sectors'],
                'x' => $place['x'] ?? null,
                'y' => $place['y'] ?? null,
                'chip' => $place['chip'] ?? null,
            ];
        }

        usort($cities, fn (array $a, array $b) => [$a['price'], $a['name']] <=> [$b['price'], $b['name']]);


        $prices = array_column($cities, 'price');
        $regions = array_values(array_unique(array_column($cities, 'region')));
        sort($regions);

        return [
            'cities' => $cities,
            'regions' => $regions,
            'featured' => array_values(array_map(
                fn (array $city) => $city['key'],
                array_filter($cities, fn (array $city) => $city['chip'] !== null)
            )),
            'totals' => [
                'cities' => count($cities),
                'sectors' => array_sum(array_column($cities, 'sectors')),
                'regions' => count($regions),
            ],
            'price' => [
                'min' => $prices === [] ? 0.0 : min($prices),
                'max' => $prices === [] ? 0.0 : max(array_column($cities, 'price_max')),
            ],
        ];
    }

    /**
     * Add the destinations that have no sector of their own but are served on
     * another city's leg, so the map can still quote them a price.
     *
     * @param  array<string, array<string, mixed>>  $grouped
     * @return array<string, array<string, mixed>>
     */
    private static function withInheritedCities(array $grouped): array
    {
        if ($grouped === []) {
            return $grouped;
        }

        foreach (MoroccoCityMap::inherited() as $key => $definition) {
            $source = $grouped[$definition['rates_from']] ?? null;

            if (isset($grouped[$key]) || $source === null) {
                continue;
            }

            $region = $definition['region'] ?? $source['region_key'];

            $grouped[$key] = [
                'key' => $key,
                'name' => MoroccoCityMap::label($key),
                'region' => MoroccoCityMap::region($region),
                'region_key' => MoroccoCityMap::regionKey($region),
                'min' => $source['min'],
                'max' => $source['max'],
                'delay' => $source['delay'],
                'sectors' => 0,
            ];
        }

        return $grouped;
    }
}
