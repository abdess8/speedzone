<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public const DEMO_PASSWORD = '12345678';

    public function run(): void
    {
        $password = Hash::make(self::DEMO_PASSWORD);

        $casablancaId = City::query()->where('name', 'Casablanca')->value('id');

        $adminRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $driverRole = Role::query()->where('name', Role::DRIVER)->firstOrFail();
        $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@speedzone.ma'],
            [
                'name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => $password,
                'email_verified_at' => now(),
                'role_id' => $adminRole->id,
                'city_id' => $casablancaId,
                'phone_number' => '0600000001',
                'pickup_address_1' => 'SpeedZone HQ, Casablanca',
            ]
        );
        $superAdmin->roles()->sync([$adminRole->id]);

        // Ensure super admin holds every permission (Admin role already syncs all in RolePermissionSeeder).
        $allPermissionIds = Permission::query()->pluck('id')->all();
        $adminRole->permissions()->sync($allPermissionIds);

        $driver = User::updateOrCreate(
            ['email' => 'driver@speedzone.ma'],
            [
                'name' => 'Ahmed Driver',
                'first_name' => 'Ahmed',
                'last_name' => 'Driver',
                'password' => $password,
                'email_verified_at' => now(),
                'role_id' => $driverRole->id,
                'city_id' => $casablancaId,
                'phone_number' => '0600000002',
            ]
        );
        $driver->roles()->sync([$driverRole->id]);

        $sellerNames = [
            ['Sara', 'Alami', 'seller1@speedzone.ma', '0600000101'],
            ['Omar', 'Benjelloun', 'seller2@speedzone.ma', '0600000102'],
            ['Laila', 'Fassi', 'seller3@speedzone.ma', '0600000103'],
            ['Mehdi', 'Tazi', 'seller4@speedzone.ma', '0600000104'],
            ['Nour', 'Chakir', 'seller5@speedzone.ma', '0600000105'],
        ];

        foreach ($sellerNames as [$first, $last, $email, $phone]) {
            $seller = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "{$first} {$last}",
                    'first_name' => $first,
                    'last_name' => $last,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'role_id' => $sellerRole->id,
                    'city_id' => $casablancaId,
                    'phone_number' => $phone,
                    'pickup_address_1' => "{$first} Store, Casablanca",
                    'pickup_address_2' => "{$first} Warehouse, Mohammedia",
                ]
            );
            $seller->roles()->sync([$sellerRole->id]);
        }

        $this->command?->info('Demo users seeded (password: '.self::DEMO_PASSWORD.')');
        $this->command?->info('  Super Admin: superadmin@speedzone.ma');
        $this->command?->info('  Driver:      driver@speedzone.ma');
        $this->command?->info('  Sellers:     seller1@speedzone.ma … seller5@speedzone.ma');
    }
}
