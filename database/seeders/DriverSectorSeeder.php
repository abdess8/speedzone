<?php

namespace Database\Seeders;

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

        $sectors = Sector::query()->where('is_active', true)->get();

        if ($sectors->isEmpty()) {
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

        foreach ($drivers as $driver) {
            // Assign each driver a random handful of sectors.
            $assigned = $sectors->random(min(3, $sectors->count()));

            $payload = $assigned->mapWithKeys(
                fn (Sector $sector) => [$sector->id => ['assigned_at' => now()]]
            )->all();

            $driver->sectors()->syncWithoutDetaching($payload);
        }
    }
}
