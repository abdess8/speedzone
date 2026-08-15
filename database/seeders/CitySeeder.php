<?php

namespace Database\Seeders;

use App\Models\City;
use Database\Seeders\Support\CitySectorDataset;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Cities where we run a warehouse vendors may store their stock in.
     *
     * The three large logistics hubs, which is enough for a demo to show both
     * halves of the fulfilment flow: an order delivered in the depot's own city
     * goes straight out, one bound elsewhere leaves on a transfer.
     *
     * @var array<int, string>
     */
    private const STOCK_HUBS = ['CASV', 'MARV', 'TANV'];

    public function run(): void
    {
        foreach (CitySectorDataset::cities() as $city) {
            City::withTrashed()->updateOrCreate(
                ['code' => $city['code']],
                $city + [
                    'is_active' => true,
                    // Stated either way, so a re-run also takes a depot back off a
                    // city that has been dropped from the list.
                    'is_stock_hub' => in_array($city['code'], self::STOCK_HUBS, true),
                    'deleted_at' => null,
                ]
            );
        }
    }
}
