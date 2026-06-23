<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        // Sectors keyed by city name, each with its own delivery price (MAD).
        $sectorsByCity = [
            'Casablanca' => [
                ['name' => 'Maarif', 'delivery_price' => 35],
                ['name' => 'Ain Sebaa', 'delivery_price' => 40],
                ['name' => 'Sidi Maarouf', 'delivery_price' => 45],
                ['name' => 'Bourgogne', 'delivery_price' => 35],
                ['name' => 'Anfa', 'delivery_price' => 40],
            ],
            'Rabat' => [
                ['name' => 'Agdal', 'delivery_price' => 30],
                ['name' => 'Hay Riad', 'delivery_price' => 35],
                ['name' => 'Hassan', 'delivery_price' => 30],
                ['name' => 'Souissi', 'delivery_price' => 35],
            ],
            'Marrakech' => [
                ['name' => 'Gueliz', 'delivery_price' => 35],
                ['name' => 'Medina', 'delivery_price' => 40],
                ['name' => 'Hivernage', 'delivery_price' => 40],
            ],
            'Fès' => [
                ['name' => 'Ville Nouvelle', 'delivery_price' => 35],
                ['name' => 'Medina', 'delivery_price' => 40],
            ],
            'Tanger' => [
                ['name' => 'Centre Ville', 'delivery_price' => 40],
                ['name' => 'Malabata', 'delivery_price' => 45],
            ],
            'Agadir' => [
                ['name' => 'Centre', 'delivery_price' => 45],
                ['name' => 'Founty', 'delivery_price' => 50],
            ],
        ];

        $cities = City::query()->whereIn('name', array_keys($sectorsByCity))->get()->keyBy('name');

        foreach ($sectorsByCity as $cityName => $sectors) {
            $city = $cities->get($cityName);

            if (! $city) {
                continue;
            }

            foreach ($sectors as $sector) {
                Sector::updateOrCreate(
                    ['city_id' => $city->id, 'name' => $sector['name']],
                    $sector + ['is_active' => true]
                );
            }
        }
    }
}
