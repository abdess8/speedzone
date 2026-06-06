<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'Casablanca', 'code' => 'CASA', 'region' => 'Casablanca-Settat'],
            ['name' => 'Rabat', 'code' => 'RBA', 'region' => 'Rabat-Salé-Kénitra'],
            ['name' => 'Marrakech', 'code' => 'RAK', 'region' => 'Marrakech-Safi'],
            ['name' => 'Fès', 'code' => 'FES', 'region' => 'Fès-Meknès'],
            ['name' => 'Tanger', 'code' => 'TNG', 'region' => 'Tanger-Tétouan-Al Hoceïma'],
            ['name' => 'Agadir', 'code' => 'AGA', 'region' => 'Souss-Massa'],
            ['name' => 'Meknès', 'code' => 'MEK', 'region' => 'Fès-Meknès'],
            ['name' => 'Oujda', 'code' => 'OUJ', 'region' => 'Oriental'],
            ['name' => 'Kénitra', 'code' => 'KEN', 'region' => 'Rabat-Salé-Kénitra'],
            ['name' => 'Tétouan', 'code' => 'TET', 'region' => 'Tanger-Tétouan-Al Hoceïma'],
            ['name' => 'Safi', 'code' => 'SAF', 'region' => 'Marrakech-Safi'],
            ['name' => 'El Jadida', 'code' => 'JDA', 'region' => 'Casablanca-Settat'],
            ['name' => 'Mohammedia', 'code' => 'MOH', 'region' => 'Casablanca-Settat'],
            ['name' => 'Béni Mellal', 'code' => 'BEM', 'region' => 'Béni Mellal-Khénifra'],
            ['name' => 'Nador', 'code' => 'NDR', 'region' => 'Oriental'],
        ];

        foreach ($cities as $city) {
            City::updateOrCreate(
                ['name' => $city['name']],
                $city + ['is_active' => true]
            );
        }
    }
}
