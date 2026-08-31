<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Sector;
use Database\Seeders\Support\CitySectorDataset;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectorsByCity = CitySectorDataset::sectorsByCity();

        $cities = City::query()
            ->whereIn('name', array_keys($sectorsByCity))
            ->get()
            ->keyBy('name');

        foreach ($sectorsByCity as $cityName => $sectors) {
            $city = $cities->get($cityName);

            if (! $city) {
                $this->command?->warn("Skipping sectors for unknown city [{$cityName}].");

                continue;
            }

            foreach ($sectors as $sector) {
                Sector::withTrashed()->updateOrCreate(
                    ['city_id' => $city->id, 'name' => $sector['name']],
                    $sector + ['is_active' => true, 'deleted_at' => null]
                );
            }
        }
    }
}
