<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'Casablanca', 'region' => 'Casablanca-Settat', 'delivery_price' => 25],
            ['name' => 'Rabat', 'region' => 'Rabat-Salé-Kénitra', 'delivery_price' => 30],
            ['name' => 'Marrakech', 'region' => 'Marrakech-Safi', 'delivery_price' => 35],
            ['name' => 'Fès', 'region' => 'Fès-Meknès', 'delivery_price' => 35],
            ['name' => 'Tanger', 'region' => 'Tanger-Tétouan-Al Hoceïma', 'delivery_price' => 40],
            ['name' => 'Agadir', 'region' => 'Souss-Massa', 'delivery_price' => 45],
            ['name' => 'Meknès', 'region' => 'Fès-Meknès', 'delivery_price' => 35],
            ['name' => 'Oujda', 'region' => 'Oriental', 'delivery_price' => 50],
            ['name' => 'Kénitra', 'region' => 'Rabat-Salé-Kénitra', 'delivery_price' => 30],
            ['name' => 'Tétouan', 'region' => 'Tanger-Tétouan-Al Hoceïma', 'delivery_price' => 40],
            ['name' => 'Safi', 'region' => 'Marrakech-Safi', 'delivery_price' => 40],
            ['name' => 'El Jadida', 'region' => 'Casablanca-Settat', 'delivery_price' => 30],
            ['name' => 'Mohammedia', 'region' => 'Casablanca-Settat', 'delivery_price' => 25],
            ['name' => 'Béni Mellal', 'region' => 'Béni Mellal-Khénifra', 'delivery_price' => 40],
            ['name' => 'Nador', 'region' => 'Oriental', 'delivery_price' => 50],
        ];

        foreach ($cities as $city) {
            City::updateOrCreate(
                ['name' => $city['name']],
                $city + ['is_active' => true]
            );
        }
    }
}
