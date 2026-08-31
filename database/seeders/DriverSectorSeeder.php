<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverSectorSeeder extends Seeder
{
    public function run(): void
    {
        $driverRole = Role::query()->where('name', Role::DRIVER)->first();

        if (! $driverRole) {
            $this->command?->warn('DriverSectorSeeder skipped: Driver role not found.');

            return;
        }

        $cities = City::query()
            ->whereHas('sectors', fn ($query) => $query->where('is_active', true))
            ->with(['sectors' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('id')
            ->get();

        if ($cities->isEmpty()) {
            $this->command?->warn('DriverSectorSeeder skipped: no active sectors found.');

            return;
        }

        // Prefer the demo driver created by DemoUsersSeeder.
        $drivers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::DRIVER))
            ->orderByRaw("CASE WHEN email = 'driver@speedzone.ma' THEN 0 ELSE 1 END")
            ->get();

        if ($drivers->isEmpty()) {
            $drivers = collect([
                ['first_name' => 'Hamid', 'last_name' => 'Bennis', 'email' => 'hamid.driver@example.com'],
                ['first_name' => 'Rachid', 'last_name' => 'Alaoui', 'email' => 'rachid.driver@example.com'],
                ['first_name' => 'Nadia', 'last_name' => 'Cherkaoui', 'email' => 'nadia.driver@example.com'],
            ])->map(function (array $data) use ($driverRole) {
                $driver = User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['first_name'].' '.$data['last_name'],
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'role_id' => $driverRole->id,
                    ]
                );

                $driver->roles()->syncWithoutDetaching([$driverRole->id]);

                return $driver;
            });
        }

        foreach ($drivers->values() as $index => $driver) {
            // A courier works one city. Drawing his sectors from the whole
            // country would put the same man in Tanger and Laayoune on the same
            // round, and the dispatch screens filter drivers by city.
            $city = $cities->firstWhere('id', $driver->city_id)
                ?? $cities[$index % $cities->count()];

            if ($driver->city_id !== $city->id) {
                $driver->forceFill(['city_id' => $city->id])->save();
            }

            $payload = $city->sectors->shuffle()->take(3)->mapWithKeys(
                fn (Sector $sector) => [$sector->id => ['assigned_at' => now()]]
            )->all();

            $driver->sectors()->syncWithoutDetaching($payload);
        }
    }
}
