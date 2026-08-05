<?php

namespace Database\Seeders;

use App\Enums\BillingFrequency;
use App\Enums\SellerPaymentMethod;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Role;
use App\Models\Sector;
use App\Models\Store;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DriverBillingService;
use Carbon\Carbon;
use Database\Seeders\Support\BillingDatasetGenerator;
use Database\Seeders\Support\DatasetContext;
use Database\Seeders\Support\MoroccanLocaleFaker;
use Database\Seeders\Support\OrderFlowGenerator;
use Database\Seeders\Support\SupportTicketGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Massive, business-coherent Moroccan dataset covering the whole platform:
 * users, shops, orders, pickup requests, inter-city transfers, returns, seller
 * invoices, driver cashbox settlements and support claims.
 *
 * What "coherent" means here:
 *  - every row lives between one month ago and now;
 *  - an order is created by a seller, dispatched by the back office, carried and
 *    delivered by a driver, and each status change is timestamped after the one
 *    before it;
 *  - every grouping document (pickup, transfer, invoice, driver settlement)
 *    carries 5 to 10 orders, as the business rules require;
 *  - roughly one customer, address, note and claim out of five is written in
 *    Arabic script, the rest in French / Latin transliteration.
 *
 * Usage:
 *
 *   php artisan db:seed --class=MoroccanDatasetSeeder                  # adds to existing data
 *   DATASET_PURGE=1 php artisan db:seed --class=MoroccanDatasetSeeder  # wipes operational data first
 *   DATASET_ORDERS=400 php artisan db:seed --class=MoroccanDatasetSeeder
 *   DATASET_ORDERS=50 DATASET_TICKETS=0 php artisan db:seed --class=MoroccanDatasetSeeder  # top-up only
 *
 * Reference data (roles, permissions, cities) must already be seeded:
 * `php artisan db:seed` covers it.
 */
class MoroccanDatasetSeeder extends Seeder
{
    /** Minimum number of orders generated (the last batch may overshoot). */
    private const DEFAULT_TARGET_ORDERS = 260;

    private const DEFAULT_TICKET_COUNT = 30;

    /** Age of the oldest generated row, in days. */
    private const WINDOW_DAYS = 31;

    /** Sellers the dataset spreads its orders over. */
    private const SELLER_TARGET = 14;

    /**
     * Delivery network: city => list of [sector name, delivery price in MAD].
     * Return and driver prices are derived from the delivery price.
     *
     * @var array<string, array<int, array{0: string, 1: int}>>
     */
    private const NETWORK = [
        'Casablanca' => [['Maarif', 35], ['Ain Sebaa', 40], ['Sidi Maarouf', 45], ['Hay Hassani', 38], ['Derb Sultan', 35], ['Sidi Bernoussi', 42], ['Bourgogne', 36]],
        'Rabat' => [['Agdal', 38], ['Hay Riad', 42], ['Yacoub El Mansour', 36], ['Souissi', 45], ['Océan', 35]],
        'Marrakech' => [['Gueliz', 40], ['Massira', 38], ['Daoudiate', 36], ['Mhamid', 39]],
        'Fès' => [['Ville Nouvelle', 38], ['Médina', 40], ['Saiss', 37], ['Narjiss', 39]],
        'Tanger' => [['Malabata', 42], ['Beni Makada', 38], ['Branes', 40], ['Centre Ville', 37]],
        'Agadir' => [['Talborjt', 42], ['Hay Mohammadi', 40], ['Founty', 45], ['Dakhla', 41]],
        'Meknès' => [['Hamria', 38], ['Toulal', 36], ['Marjane', 39], ['Sidi Baba', 37]],
        'Oujda' => [['Al Qods', 45], ['Sidi Yahya', 47], ['Hay Andalous', 44], ['Centre', 43]],
        'Kénitra' => [['Bir Rami', 36], ['Ouled Oujih', 35], ['Maamora', 38]],
        'Tétouan' => [['Mhannech', 44], ['Wilaya', 42], ['Samsa', 43], ['Touilaa', 45]],
        'Safi' => [['Biada', 40], ['Jrifat', 38], ['Sidi Bouzid', 39]],
        'El Jadida' => [['Salam', 38], ['Lalla Zahra', 40], ['Mohammadia', 37]],
        'Mohammedia' => [['Kasbah', 35], ['Alia', 36], ['Nassim', 38], ['Hassania', 37]],
        'Béni Mellal' => [['Mghila', 44], ['Hay El Fath', 42], ['Ouled Hamdane', 45]],
        'Nador' => [['Ihaddadene', 48], ['Selouane', 50], ['Hay Ouled Mimoun', 47]],
    ];

    /**
     * Share of the national volume absorbed by each city, used to weight both
     * delivery destinations and driver workload.
     *
     * @var array<string, int>
     */
    private const CITY_WEIGHTS = [
        'Casablanca' => 26,
        'Rabat' => 13,
        'Marrakech' => 10,
        'Tanger' => 9,
        'Fès' => 8,
        'Agadir' => 7,
        'Meknès' => 4,
        'Kénitra' => 4,
        'Oujda' => 3,
        'Tétouan' => 3,
        'El Jadida' => 3,
        'Mohammedia' => 3,
        'Safi' => 2,
        'Béni Mellal' => 2,
        'Nador' => 2,
    ];

    /**
     * Operational tables wiped by DATASET_PURGE=1. Reference data (users, roles,
     * permissions, cities, sectors, stores) is always preserved.
     *
     * @var array<int, string>
     */
    private const PURGEABLE = [
        'support_messages',
        'support_ticket_attachments',
        'support_tickets',
        'driver_invoice_transactions',
        'driver_finance_logs',
        'driver_transactions',
        'driver_invoices',
        'invoice_logs',
        'invoice_orders',
        'invoices',
        'transfer_status_histories',
        'transfer_orders',
        'transfers',
        'return_status_histories',
        'returns',
        'pickup_status_histories',
        'pickup_requests',
        'order_change_histories',
        'order_status_histories',
        'orders',
    ];

    private DatasetContext $ctx;

    public function run(): void
    {
        $this->ctx = new DatasetContext(new MoroccanLocaleFaker, Carbon::now(), self::WINDOW_DAYS);

        if (filter_var(env('DATASET_PURGE', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->purge();
        }

        $this->ensureNetwork();

        if ($this->ctx->cities->count() < 2) {
            $this->command?->error('MoroccanDatasetSeeder needs at least 2 active cities with sectors. Run `php artisan db:seed` first.');

            return;
        }

        if (! $this->ensureStaff()) {
            return;
        }

        $this->ensureSellers();

        $target = max(1, (int) env('DATASET_ORDERS', self::DEFAULT_TARGET_ORDERS));

        $this->command?->info("Génération du jeu de données marocain ({$target}+ commandes)…");

        app(OrderFlowGenerator::class, ['ctx' => $this->ctx])->run($target);
        $this->command?->info('  Commandes, ramassages, transferts et retours : OK');

        $billing = new BillingDatasetGenerator($this->ctx, app(BillingService::class), app(DriverBillingService::class));
        $billing->seedSellerInvoices();
        $billing->seedDriverInvoices();
        $this->command?->info('  Factures vendeurs et décharges de caisse : OK');

        $tickets = max(0, (int) env('DATASET_TICKETS', self::DEFAULT_TICKET_COUNT));

        if ($tickets > 0) {
            (new SupportTicketGenerator($this->ctx))->run($tickets);
            $this->command?->info('  Réclamations et fils de discussion : OK');
        }

        $this->report();
    }

    /*
    |--------------------------------------------------------------------------
    | Reference data
    |--------------------------------------------------------------------------
    */

    private function purge(): void
    {
        $this->command?->warn('DATASET_PURGE=1 — suppression des données opérationnelles existantes…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::PURGEABLE as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Make sure every served city exists with priced, active sectors.
     */
    private function ensureNetwork(): void
    {
        foreach (self::NETWORK as $cityName => $sectors) {
            /** @var City $city */
            $city = City::withTrashed()->firstWhere('name', $cityName) ?? City::create([
                'name' => $cityName,
                'code' => $this->cityCode($cityName),
                'is_active' => true,
            ]);

            if ($city->trashed()) {
                $city->restore();
            }

            if (! $city->is_active) {
                $city->forceFill(['is_active' => true])->save();
            }

            foreach ($sectors as [$sectorName, $deliveryPrice]) {
                /** @var Sector|null $sector */
                $sector = Sector::withTrashed()
                    ->where('city_id', $city->id)
                    ->where('name', $sectorName)
                    ->first();

                if (! $sector) {
                    Sector::create([
                        'city_id' => $city->id,
                        'name' => $sectorName,
                        'delivery_price' => $deliveryPrice,
                        'return_price' => round($deliveryPrice * 0.6, 2),
                        'delivery_driver_price' => round($deliveryPrice * 0.6, 2),
                        'is_active' => true,
                    ]);

                    continue;
                }

                if ($sector->trashed()) {
                    $sector->restore();
                }

                // Backfill prices left at zero by earlier seeds: an unpriced
                // sector would make invoices and driver payouts meaningless.
                $sector->forceFill([
                    'is_active' => true,
                    'delivery_price' => (float) $sector->delivery_price > 0 ? $sector->delivery_price : $deliveryPrice,
                    'return_price' => (float) $sector->return_price > 0 ? $sector->return_price : round($deliveryPrice * 0.6, 2),
                    'delivery_driver_price' => (float) $sector->delivery_driver_price > 0 ? $sector->delivery_driver_price : round($deliveryPrice * 0.6, 2),
                ])->save();
            }
        }

        $this->ctx->cities = City::query()
            ->where('is_active', true)
            ->whereHas('sectors', fn ($query) => $query->where('is_active', true))
            ->with(['sectors' => fn ($query) => $query->where('is_active', true)])
            ->get();

        $this->ctx->weightCities(self::CITY_WEIGHTS);
    }

    private function cityCode(string $cityName): string
    {
        $base = strtoupper(substr($this->ctx->faker->slug($cityName), 0, 3));
        $code = $base;
        $suffix = 1;

        while (City::withTrashed()->where('code', $code)->exists()) {
            $code = $base.$suffix++;
        }

        return $code;
    }

    /**
     * Resolve the admin, the dispatchers and one driver per city.
     */
    private function ensureStaff(): bool
    {
        $admin = User::query()->where('email', 'superadmin@speedzone.ma')->first()
            ?? User::query()->whereHas('roles', fn ($query) => $query->where('name', Role::ADMIN))->first();

        if (! $admin) {
            $this->command?->error('No admin user found. Run `php artisan db:seed` (DemoUsersSeeder) first.');

            return false;
        }

        $this->ctx->admin = $admin;

        $dispatcherRole = Role::query()->where('name', Role::DISPATCHER)->first();

        foreach ([
            ['dispatcher.casa@speedzone.ma', 'Casablanca'],
            ['dispatcher.rabat@speedzone.ma', 'Rabat'],
        ] as [$email, $cityName]) {
            $city = $this->ctx->cities->firstWhere('name', $cityName) ?? $this->ctx->anyCity();
            $person = $this->ctx->faker->person(false);

            $dispatcher = User::query()->firstOrNew(['email' => $email]);

            if (! $dispatcher->exists) {
                $dispatcher->fill([
                    'name' => "{$person['first_name']} {$person['last_name']}",
                    'first_name' => $person['first_name'],
                    'last_name' => $person['last_name'],
                    'password' => Hash::make('12345678'),
                    'phone_number' => $this->ctx->faker->phone(),
                    'cin' => $this->ctx->faker->cin(),
                ]);
            }

            $dispatcher->forceFill([
                'role_id' => $dispatcherRole?->id,
                'city_id' => $city->id,
                'status' => UserStatus::Active->value,
                'email_verified_at' => $dispatcher->email_verified_at ?? $this->ctx->windowStart,
                'address' => "Hub {$city->name}, Zone Logistique",
            ])->save();

            if ($dispatcherRole) {
                $dispatcher->roles()->syncWithoutDetaching([$dispatcherRole->id]);
            }

            $this->ctx->dispatchers->push($dispatcher);
        }

        $driverRole = Role::query()->where('name', Role::DRIVER)->first();

        foreach ($this->ctx->cities as $city) {
            $existing = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', Role::DRIVER))
                ->where('city_id', $city->id)
                ->get();

            if ($existing->isEmpty()) {
                $existing = collect([$this->createDriver($city, $driverRole)]);
            }

            foreach ($existing as $driver) {
                // A driver serves every sector of his city.
                $driver->sectors()->syncWithoutDetaching(
                    $city->sectors->mapWithKeys(fn (Sector $sector) => [
                        $sector->id => ['assigned_at' => $this->ctx->windowStart],
                    ])->all()
                );

                $this->ctx->driversByCity[$city->id][] = $driver;

                if (! $this->ctx->drivers->contains('id', $driver->id)) {
                    $this->ctx->drivers->push($driver);
                }
            }
        }

        return true;
    }

    private function createDriver(City $city, ?Role $driverRole): User
    {
        $person = $this->ctx->faker->person();
        $slug = $this->ctx->faker->slug($city->name);

        $driver = User::create([
            'name' => "{$person['first_name']} {$person['last_name']}",
            'first_name' => $person['first_name'],
            'last_name' => $person['last_name'],
            'email' => "livreur.{$slug}@speedzone.ma",
            'password' => Hash::make('12345678'),
            'phone_number' => $this->ctx->faker->phone(),
            'cin' => $this->ctx->faker->cin(),
            'address' => $this->ctx->faker->address($city->name, $person['arabic']),
        ]);

        $driver->forceFill([
            'role_id' => $driverRole?->id,
            'city_id' => $city->id,
            'status' => UserStatus::Active->value,
            'email_verified_at' => $this->ctx->windowStart,
        ])->save();

        if ($driverRole) {
            $driver->roles()->syncWithoutDetaching([$driverRole->id]);
        }

        $this->ctx->bump('drivers_created');

        return $driver;
    }

    /**
     * Resolve the sellers, top the pool up to SELLER_TARGET and make sure each
     * one has a complete billing profile and a default shop.
     */
    private function ensureSellers(): void
    {
        $sellerRole = Role::query()->where('name', Role::SELLER)->first();

        $sellers = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', Role::SELLER))
            ->whereNull('parent_user_id')
            ->get();

        $missing = max(0, self::SELLER_TARGET - $sellers->count());

        for ($index = 0; $index < $missing; $index++) {
            $sellers->push($this->createSeller($sellerRole));
        }

        foreach ($sellers as $seller) {
            $city = $this->ctx->city($seller->city_id) ?? $this->ctx->anyCity();
            $frequency = $this->ctx->faker->pick([
                BillingFrequency::WEEKLY,
                BillingFrequency::BIWEEKLY,
                BillingFrequency::MONTHLY,
                BillingFrequency::MONTHLY,
            ]);

            $seller->forceFill([
                'city_id' => $city->id,
                'status' => UserStatus::Active->value,
                'phone_number' => $seller->phone_number ?? $this->ctx->faker->phone(),
                'cin' => $seller->cin ?? $this->ctx->faker->cin(),
                'ice_number' => $seller->ice_number ?? $this->ctx->faker->iceNumber(),
                'pickup_address_1' => $seller->pickup_address_1 ?? ('Boutique '.$seller->name.', '.$city->name),
                'billing_enabled' => true,
                'billing_frequency' => $frequency->value,
                'next_billing_date' => $frequency->nextDateFrom(Carbon::today())?->toDateString(),
                'payment_method' => $seller->payment_method ?? $this->ctx->faker->pick(SellerPaymentMethod::cases())->value,
                'bank_name' => $seller->bank_name ?? $this->ctx->faker->bank(),
                'rib' => $seller->rib ?? $this->ctx->faker->rib(),
            ])->save();

            $this->ctx->stores[$seller->id] = $this->ensureStore($seller, $city);
            $this->ctx->sellers->push($seller);
        }
    }

    private function createSeller(?Role $sellerRole): User
    {
        $arabic = $this->ctx->faker->arabic();
        $person = $this->ctx->faker->person($arabic);
        $city = $this->ctx->anyCity();
        $handle = $this->ctx->faker->slug($person['first_name'].' '.$person['last_name']) ?: 'boutique';

        $email = $handle.random_int(100, 999).'@boutique.ma';
        while (User::query()->where('email', $email)->exists()) {
            $email = $handle.random_int(1000, 9999).'@boutique.ma';
        }

        $seller = User::create([
            'name' => "{$person['first_name']} {$person['last_name']}",
            'first_name' => $person['first_name'],
            'last_name' => $person['last_name'],
            'email' => $email,
            'password' => Hash::make('12345678'),
            'phone_number' => $this->ctx->faker->phone(),
            'address' => $this->ctx->faker->address($city->name, $arabic),
            'cin' => $this->ctx->faker->cin(),
        ]);

        $seller->forceFill([
            'role_id' => $sellerRole?->id,
            'city_id' => $city->id,
            'status' => UserStatus::Active->value,
            'email_verified_at' => $this->ctx->windowStart,
            'approved_at' => $this->ctx->windowStart,
            'approved_by' => $this->ctx->admin->id,
        ])->save();

        if ($sellerRole) {
            $seller->roles()->syncWithoutDetaching([$sellerRole->id]);
        }

        $this->ctx->bump('sellers_created');

        return $seller;
    }

    private function ensureStore(User $seller, City $city): Store
    {
        $store = Store::query()->ownedBy($seller->id)->orderByDesc('is_default')->first();

        if (! $store) {
            [$name, $category] = $this->ctx->faker->shop($this->ctx->faker->arabic());

            $store = Store::create([
                'owner_id' => $seller->id,
                'name' => Store::query()->where('owner_id', $seller->id)->where('name', $name)->exists()
                    ? $name.' '.random_int(2, 99)
                    : $name,
                'category' => $category,
                'contact_name' => $seller->name,
                'contact_phone' => $seller->phone_number,
                'contact_email' => $seller->email,
                'city_id' => $city->id,
                'address' => $seller->address ?? $this->ctx->faker->address($city->name),
                'pickup_address_1' => $seller->pickup_address_1,
                'is_default' => true,
                'is_active' => true,
            ]);

            $this->ctx->bump('stores_created');
        }

        $store->users()->syncWithoutDetaching([$seller->id]);

        return $store;
    }

    /*
    |--------------------------------------------------------------------------
    | Reporting
    |--------------------------------------------------------------------------
    */

    private function report(): void
    {
        $stats = $this->ctx->stats;
        $orders = max(1, $stats['orders'] ?? 0);

        $rows = [
            ['Commandes', $stats['orders'] ?? 0],
            ['— dont livrées', $stats['delivered'] ?? 0],
            ['— dont non livrées', $stats['failed'] ?? 0],
            ['— dont refusées', $stats['rejected'] ?? 0],
            ['— dont annulées', $stats['canceled'] ?? 0],
            ['— dont retournées au vendeur', $stats['returned_orders'] ?? 0],
            ['— dont client/adresse en arabe', ($stats['arabic_orders'] ?? 0).' ('.round((($stats['arabic_orders'] ?? 0) / $orders) * 100).'%)'],
            ['Historiques de statut', $stats['status_histories'] ?? 0],
            ['Historiques de modification', $stats['order_changes'] ?? 0],
            ['Demandes de ramassage', $stats['pickups'] ?? 0],
            ['Bordereaux de transfert', $stats['transfers'] ?? 0],
            ['Retours', $stats['returns'] ?? 0],
            ['Factures vendeurs', ($stats['invoices'] ?? 0).' (dont '.($stats['invoices_paid'] ?? 0).' payées)'],
            ['Lignes de facture', $stats['invoice_lines'] ?? 0],
            ['Transactions livreurs', $stats['driver_transactions'] ?? 0],
            ['Décharges de caisse livreurs', ($stats['driver_invoices'] ?? 0).' (dont '.($stats['driver_invoices_paid'] ?? 0).' payées)'],
            ['Réclamations', ($stats['tickets'] ?? 0).' (dont '.($stats['arabic_tickets'] ?? 0).' en arabe)'],
            ['Messages de réclamation', $stats['ticket_messages'] ?? 0],
            ['Pièces jointes', $stats['ticket_attachments'] ?? 0],
            ['Vendeurs créés', $stats['sellers_created'] ?? 0],
            ['Boutiques créées', $stats['stores_created'] ?? 0],
            ['Livreurs créés', $stats['drivers_created'] ?? 0],
        ];

        $this->command?->newLine();
        $this->command?->table(['Jeu de données généré', 'Volume'], $rows);
    }
}
