<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementSource;
use App\Enums\StockReceptionStatus;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockReception;
use App\Models\StockReceptionItem;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderPreparationService;
use App\Services\OrderService;
use App\Services\OrderStockService;
use App\Services\ProductService;
use App\Services\StockLedgerService;
use App\Services\StockReceptionService;
use App\Services\TeamRoleService;
use App\Services\TeamService;
use App\Support\StockPermissions;
use App\Support\StoreContext;
use Carbon\Carbon;
use Database\Seeders\Support\MoroccanLocaleFaker;
use Database\Seeders\Support\StockCatalogFaker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Vendor fulfilment dataset: two stock-enabled shops, their catalogs, the
 * shipments they sent us, their inventory corrections and the orders they picked
 * from stock.
 *
 * Everything is written through the real services (ProductService,
 * StockReceptionService, StockLedgerService, OrderService), never by inserting
 * rows: a seeded shelf therefore satisfies the same invariant as a production
 * one — replaying `stock_adjustments` reproduces `stock_quantity` exactly, and
 * every movement points at the document that caused it.
 *
 * Usage:
 *
 *   php artisan db:seed --class=StockDatasetSeeder
 *   STOCK_PURGE=1 php artisan db:seed --class=StockDatasetSeeder   # wipe stock data first
 *   STOCK_ORDERS=30 php artisan db:seed --class=StockDatasetSeeder
 *
 * Reference data (roles, permissions, cities, sectors) must already be seeded.
 */
class StockDatasetSeeder extends Seeder
{
    /**
     * The two vendors the module is demonstrated on: email, first name, last
     * name, shop, trade, home city, depot city.
     *
     * The second shop warehouses away from home on purpose. A parcel it sells
     * leaves Marrakech, not Rabat, and a dataset where the two always coincided
     * would let a bug that reads the vendor's city instead of the depot pass
     * unnoticed.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>
     */
    private const VENDORS = [
        // Shop names deliberately absent from MoroccanLocaleFaker's pool: the wider
        // dataset draws its shops from there, and two "Atlas Cosmétique" in the
        // admin list would make the stock demo impossible to pick out.
        ['stock1@oowlmedia.com', 'Yasmine', 'Berrada', 'Dar Argania', 'Cosmétique', 'Casablanca', 'Casablanca'],
        ['stock2@oowlmedia.com', 'Anas', 'Sekkat', 'Medina Tech', 'Électronique', 'Rabat', 'Marrakech'],
    ];

    private const DEMO_PASSWORD = '12345678';

    private const PRODUCTS_PER_VENDOR = 20;

    private const DEFAULT_ORDERS_PER_VENDOR = 12;

    /** Age of the oldest generated row, in days. */
    private const WINDOW_DAYS = 45;

    /**
     * The window is cut into phases that do not overlap, so the dataset reads as
     * one story — the catalog is keyed in, then stocked, then counted, then sold —
     * and every row's id agrees with its date. Each entry is [from, to] in days
     * ago; the shipment plan below fills the gap between the catalog and the
     * counts.
     */
    private const PHASE_CATALOG = [44, 41];

    private const PHASE_COUNTS = [10, 8];

    private const PHASE_ORDERS = [7, 2];

    private const PHASE_SELL_OUT = [1, 0];

    /**
     * Share of the orders the depot has already packed.
     *
     * The remainder stays in AWAITING_PREPARATION so the picking bench is never
     * an empty screen on a fresh install.
     */
    private const PACKED_SHARE = 2 / 3;

    /**
     * Share of the baskets delivered in the shop's own depot city.
     *
     * A shop sells disproportionately close to home, and with fifteen served
     * cities a uniform draw would leave a demo where the depot almost never
     * coincides with the customer — so the "packed and gone" half of the
     * fulfilment flow would never show up in the data.
     */
    private const HOME_CITY_SHARE = 35;

    /**
     * Tables wiped by STOCK_PURGE=1.
     *
     * Orders are left alone: they belong to the wider dataset, so only their
     * catalog lines are removed and the stock they consumed is dropped with the
     * ledger.
     *
     * @var array<int, string>
     */
    private const PURGEABLE = [
        'order_items',
        'stock_adjustments',
        'stock_reception_status_histories',
        'stock_reception_items',
        'stock_receptions',
        'product_histories',
        'products',
    ];

    private MoroccanLocaleFaker $faker;

    private StockCatalogFaker $catalog;

    private Carbon $now;

    private Carbon $windowStart;

    private User $hubAgent;

    /** @var array<int|string, User> */
    private array $collectorMemo = [];

    /** @var array<string, int> */
    private array $stats = [];

    public function run(): void
    {
        $this->faker = new MoroccanLocaleFaker;
        $this->catalog = new StockCatalogFaker($this->faker);
        $this->now = Carbon::now();
        $this->windowStart = $this->now->copy()->subDays(self::WINDOW_DAYS)->startOfDay();

        if (filter_var(env('STOCK_PURGE', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->purge();
        }

        $agent = $this->resolveHubAgent();

        if (! $agent) {
            $this->command?->error(
                'Aucun compte capable de réceptionner du stock. Lancez `php artisan db:seed` d\'abord.'
            );

            return;
        }

        $this->hubAgent = $agent;

        $cities = $this->servedCities();

        if ($cities->isEmpty()) {
            $this->command?->error(
                'Aucune ville active avec des secteurs. Lancez `php artisan db:seed` d\'abord.'
            );

            return;
        }

        // Stock has to live somewhere: without a depot city no shipment can be
        // addressed and no order can name the city it leaves from.
        if (City::query()->stockHub()->where('is_active', true)->doesntExist()) {
            $this->command?->error(
                'Aucune ville marquée « hub de stock ». Lancez `php artisan db:seed --class=CitySeeder` d\'abord.'
            );

            return;
        }

        $ordersPerVendor = max(0, (int) env('STOCK_ORDERS', self::DEFAULT_ORDERS_PER_VENDOR));

        foreach (self::VENDORS as $index => $definition) {
            [$vendor, $store] = $this->ensureVendor($definition, $cities);

            $this->command?->info("Vendeur {$vendor->email} — boutique « {$store->name} »");

            // Every generator below runs with the vendor's store enforced, exactly
            // as a request from him would: BelongsToStore then files each row under
            // the right shop and the services need no seeder-specific branch.
            $this->withinStore($vendor, $store, function () use ($vendor, $store, $cities, $ordersPerVendor, $index): void {
                $products = $this->seedCatalog($vendor);
                $this->command?->info('  Catalogue : '.$products->count().' références');

                $this->seedReceptions($vendor, $products);
                $this->command?->info('  Réceptions de stock : OK');

                $this->seedInventoryCounts($vendor, $products);
                $this->command?->info('  Inventaires et corrections : OK');

                if ($ordersPerVendor > 0 && ! $this->hasPickedOrders()) {
                    $this->seedStockOrders($vendor, $cities, $ordersPerVendor);
                    $this->seedSellOuts($vendor, $cities);
                    $this->command?->info('  Commandes depuis le stock : OK');

                    $this->seedPreparation();
                    $this->command?->info('  Préparation au dépôt : OK');
                }

                // One quarantined reference on the second shop, so the hub-side
                // `stock.admin_override` screens have something to show.
                if ($index === 1) {
                    $this->quarantineOne($products);
                }

                $this->ensureStockKeeper($vendor, $store, $index);
            });
        }

        $this->report();
    }

    /*
    |--------------------------------------------------------------------------
    | Actors
    |--------------------------------------------------------------------------
    */

    private function purge(): void
    {
        $this->command?->warn('STOCK_PURGE=1 — suppression des données de stock existantes…');

        // Orders picked from the catalog go with it: one whose lines are gone keeps
        // an amount that nothing justifies any more. Read before the truncation,
        // which is what erases the link.
        $picked = DB::table('order_items')->distinct()->pluck('order_id');

        // Except those already gathered into a document this seeder does not own —
        // deleting them would tear a hole in a transfer, an invoice or a payout.
        $grouped = collect(['transfer_orders', 'invoice_orders', 'returns', 'driver_transactions'])
            ->flatMap(fn (string $table) => DB::table($table)->whereIn('order_id', $picked)->pluck('order_id'))
            ->unique();

        $removable = $picked->diff($grouped);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::PURGEABLE as $table) {
            DB::table($table)->truncate();
        }

        if ($removable->isNotEmpty()) {
            DB::table('order_status_histories')->whereIn('order_id', $removable)->delete();
            DB::table('order_change_histories')->whereIn('order_id', $removable)->delete();
            DB::table('orders')->whereIn('id', $removable)->delete();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command?->warn("  {$removable->count()} commande(s) issue(s) du stock supprimée(s).");

        if ($grouped->isNotEmpty()) {
            $this->command?->warn(
                "  {$grouped->count()} conservée(s) : déjà rattachée(s) à un bordereau, une facture ou une décharge."
            );
        }
    }

    /**
     * The back-office account that counts shipments in.
     *
     * A dispatcher when there is one — that is who does it in production — and
     * the admin otherwise, since he holds every grant.
     */
    private function resolveHubAgent(): ?User
    {
        $withGrant = fn (string $permission) => User::query()
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', $permission))
            ->whereNull('parent_user_id');

        return $withGrant(StockPermissions::RECEIVE_INBOUND)->first()
            ?? $withGrant(StockPermissions::ADMIN_OVERRIDE)->first()
            ?? User::query()->whereHas('roles', fn ($query) => $query->where('name', Role::ADMIN))->first();
    }

    /**
     * Somebody who can drive out to this shipment's shop.
     *
     * Resolved per collection city and memoized, so the demo shows a local driver
     * on each round rather than one hero collecting across the country. Falls back
     * to the receiving agent on an install where no driver covers the city — the
     * point of the fixture is a populated journey, not a staffing model.
     */
    private function collectorFor(StockReception $reception): User
    {
        $cityId = $reception->pickupCityId();
        $key = $cityId ?? 'any';

        return $this->collectorMemo[$key] ??= User::query()
            ->whereHas(
                'roles.permissions',
                fn ($query) => $query->where('name', StockPermissions::COLLECT_INBOUND)
            )
            ->when($cityId !== null, fn ($query) => $query->coveringCity($cityId))
            ->whereNull('parent_user_id')
            ->orderBy('id')
            ->first()
            ?? $this->hubAgent;
    }

    /**
     * The back-office account that packs orders.
     *
     * Resolved separately from the receiving agent, because counting a shipment
     * in and picking an order are two different grants and the same person does
     * not necessarily hold both.
     */
    private function resolvePacker(): User
    {
        return User::query()
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', 'orders.transition.to_prepared'))
            ->whereNull('parent_user_id')
            ->first()
            ?? $this->hubAgent;
    }

    /**
     * @return Collection<int, City>
     */
    private function servedCities()
    {
        return City::query()
            ->where('is_active', true)
            ->whereHas('sectors', fn ($query) => $query->where('is_active', true))
            ->with(['sectors' => fn ($query) => $query->where('is_active', true)])
            ->get();
    }

    /**
     * Resolve — or create — one of the two stock vendors and his default shop.
     *
     * The seller role already carries the five vendor stock grants (see
     * RolePermissionMatrix), so holding that role *is* holding the permissions;
     * this only makes sure the account is active and attached to a shop, since
     * stock cannot exist outside one.
     *
     * @param  array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}  $definition
     * @param  Collection<int, City>  $cities
     * @return array{0: User, 1: Store}
     */
    private function ensureVendor(array $definition, $cities): array
    {
        [$email, $firstName, $lastName, $shopName, $shopCategory, $cityName, $depotName] = $definition;

        $sellerRole = Role::query()->where('name', Role::SELLER)->firstOrFail();
        $city = $cities->firstWhere('name', $cityName) ?? $cities->first();
        $depot = $this->resolveDepot($depotName);

        $vendor = User::query()->firstOrNew(['email' => $email]);

        if (! $vendor->exists) {
            $vendor->fill([
                'name' => "{$firstName} {$lastName}",
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'phone_number' => $this->faker->phone(),
                'cin' => $this->faker->cin(),
            ]);

            $this->bump('vendors_created');
        }

        $vendor->forceFill([
            'role_id' => $sellerRole->id,
            'city_id' => $city->id,
            'status' => UserStatus::Active->value,
            'email_verified_at' => $vendor->email_verified_at ?? $this->windowStart,
            'approved_at' => $vendor->approved_at ?? $this->windowStart,
            'ice_number' => $vendor->ice_number ?? $this->faker->iceNumber(),
            'pickup_address_1' => $vendor->pickup_address_1 ?? "{$shopName}, {$city->name}",
        ])->save();

        $vendor->roles()->syncWithoutDetaching([$sellerRole->id]);

        $store = Store::query()->ownedBy($vendor->id)->where('name', $shopName)->first()
            ?? Store::query()->ownedBy($vendor->id)->orderByDesc('is_default')->first();

        if (! $store) {
            $store = Store::create([
                'owner_id' => $vendor->id,
                'name' => $shopName,
                'category' => $shopCategory,
                'contact_name' => $vendor->name,
                'contact_phone' => $vendor->phone_number,
                'contact_email' => $vendor->email,
                'city_id' => $city->id,
                'address' => $this->faker->address($city->name),
                'pickup_address_1' => $vendor->pickup_address_1,
                'is_default' => true,
                'is_active' => true,
            ]);

            $this->bump('stores_created');
        }

        // Where this shop's stock is held. Set here rather than left to the first
        // shipment so the catalog, the counts and the orders below all agree on a
        // single depot from the outset.
        if ($store->stock_hub_city_id === null && $depot) {
            $store->forceFill(['stock_hub_city_id' => $depot->id])->save();
        }

        $store->users()->syncWithoutDetaching([$vendor->id]);

        return [$vendor->fresh(['roles.permissions']), $store->fresh()];
    }

    /**
     * The depot a shop warehouses in.
     *
     * Falls back to any open depot, because the city list is configurable and a
     * demo must not break just because the preferred one was never flagged.
     */
    private function resolveDepot(string $preferred): ?City
    {
        $hubs = City::query()->stockHub()->where('is_active', true);

        return $hubs->clone()->where('name', $preferred)->first()
            ?? $hubs->clone()->orderBy('name')->first();
    }

    /**
     * A team member holding a strict subset of his employer's stock grants.
     *
     * The two shops delegate differently on purpose, so the permission matrix is
     * visible in the data rather than only in the documentation: one hires a
     * stock keeper who counts and ships but cannot price, the other a salesperson
     * who picks orders but never touches the recorded quantities.
     */
    private function ensureStockKeeper(User $vendor, Store $store, int $index): void
    {
        [$label, $email, $grants] = $index === 0
            ? [
                'Magasinier',
                'magasinier@dar-argania.ma',
                [
                    StockPermissions::VIEW,
                    StockPermissions::CREATE_INBOUND,
                    StockPermissions::ADJUST,
                ],
            ]
            : [
                'Vendeur comptoir',
                'comptoir@medina-tech.ma',
                [
                    StockPermissions::VIEW,
                    StockPermissions::ORDERS_CREATE_WITH_STOCK,
                    'orders.create',
                    'orders.read.own',
                ],
            ];

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $role = app(TeamRoleService::class)->create($vendor, $label, $grants);
        $person = $this->faker->person(false);

        app(TeamService::class)->create($vendor, [
            'first_name' => $person['first_name'],
            'last_name' => $person['last_name'],
            'email' => $email,
            'phone_number' => $this->faker->phone(),
            'password' => self::DEMO_PASSWORD,
            'store_ids' => [$store->id],
            'role_ids' => [$role->id],
        ]);

        $this->bump('team_members');
    }

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, Product>
     */
    private function seedCatalog(User $vendor)
    {
        $existing = Product::query()->get();

        if ($existing->count() >= self::PRODUCTS_PER_VENDOR) {
            return $existing;
        }

        $store = Store::query()->whereKey(app(StoreContext::class)->id())->first();
        $service = app(ProductService::class);
        $missing = self::PRODUCTS_PER_VENDOR - $existing->count();
        $dates = $this->moments($missing, ...self::PHASE_CATALOG);

        foreach ($this->catalog->catalog($store?->category ?? 'Cosmétique', $missing) as $index => $payload) {
            // A catalog exists before it is stocked, so references are dated at the
            // very start of the window: every movement that follows then lands
            // after the product that carries it.
            $createdAt = $dates[$index] ?? $this->momentBetween(...self::PHASE_CATALOG);

            $product = $service->create($payload, $vendor);

            $this->stamp('products', $product->id, $createdAt);
            $this->stampSince('product_histories', $product->histories()->min('id'), $createdAt);

            $this->bump('products');
        }

        return Product::query()->get();
    }

    /**
     * Quarantine one reference, as a hub operator would on a defective batch.
     *
     * @param  Collection<int, Product>  $products
     */
    private function quarantineOne($products): void
    {
        $target = $products->where('is_active', true)->first();

        if (! $target || $target->is_blocked) {
            return;
        }

        app(ProductService::class)->setBlocked(
            product: $target,
            blocked: true,
            reason: 'Lot défectueux signalé par le dépôt : emballages percés.',
            actor: $this->hubAgent,
        );

        $this->bump('blocked_products');
    }

    /*
    |--------------------------------------------------------------------------
    | Inbound shipments
    |--------------------------------------------------------------------------
    */

    /**
     * Shipments frozen at every stage of the journey.
     *
     * One per status, so no screen in the module opens empty on a fresh install:
     * three counted in (which is what actually puts stock on the shelf), one on the
     * road for the depot to receive, one in a collector's van, one waiting for
     * somebody to drive out, one still a draft the vendor is editing.
     *
     * @param  Collection<int, Product>  $products
     */
    private function seedReceptions(User $vendor, $products): void
    {
        if ($products->isEmpty() || StockReception::query()->exists()) {
            return;
        }

        $service = app(StockReceptionService::class);
        $depotId = Store::query()->whereKey(app(StoreContext::class)->id())->value('stock_hub_city_id');

        // Oldest first: the opening shipment is the largest, later ones are the
        // reassorts a shop sends once it knows what sells.
        $plan = [
            [StockReceptionStatus::VALIDATED, 40, 12, 25, 60],
            [StockReceptionStatus::VALIDATED, 26, 8, 12, 30],
            [StockReceptionStatus::VALIDATED, 16, 6, 8, 24],
            [StockReceptionStatus::IN_TRANSIT, 6, 5, 10, 30],
            [StockReceptionStatus::COLLECTED, 3, 4, 8, 20],
            [StockReceptionStatus::AWAITING_PICKUP, 2, 5, 10, 30],
            [StockReceptionStatus::DRAFT, 1, 4, 6, 18],
        ];

        $previous = null;

        foreach ($plan as [$status, $daysAgo, $lineCount, $minQuantity, $maxQuantity]) {
            $sentAt = $this->momentBetween(max(0, $daysAgo - 2), $daysAgo);

            // Windows are close enough to overlap on the recent end of the plan, and
            // a queue whose ids and dates disagree reads as corrupt. Keep the shop's
            // shipments in the order it actually sent them.
            if ($previous && $sentAt->lessThanOrEqualTo($previous)) {
                $sentAt = $this->clamp($previous->copy()->addHours(random_int(4, 14)));
            }

            $previous = $sentAt;
            $lines = $products->shuffle()->take($lineCount);

            if ($lines->isEmpty()) {
                continue;
            }

            $isDraft = $status === StockReceptionStatus::DRAFT;

            $reception = $service->create([
                'status' => $isDraft
                    ? StockReceptionStatus::DRAFT->value
                    : StockReceptionStatus::AWAITING_PICKUP->value,
                'sent_at' => $isDraft ? null : $sentAt->toDateString(),
                // Even the draft names it: a vendor picks his depot once, and the
                // form then shows the choice locked on every later shipment.
                'destination_city_id' => $depotId,
                'sending_notes' => $this->catalog->sendingNote(),
                'items' => $lines->map(fn (Product $product) => [
                    'product_id' => $product->id,
                    'quantity_sent' => random_int($minQuantity, $maxQuantity),
                ])->values()->all(),
            ], $vendor);

            $this->stamp('stock_receptions', $reception->id, $sentAt);
            $this->stampJournal($reception, $sentAt);
            $this->bump('receptions');

            if ($isDraft || $status === StockReceptionStatus::AWAITING_PICKUP) {
                continue;
            }

            $collectedAt = $this->collectFor($service, $reception, $sentAt);

            if ($status === StockReceptionStatus::COLLECTED) {
                continue;
            }

            $dispatchedAt = $this->clamp($collectedAt->copy()->addHours(random_int(2, 9)));
            $service->dispatchToDepot($reception->refresh(), $this->collectorFor($reception));
            DB::table('stock_receptions')
                ->where('id', $reception->id)
                ->update(['dispatched_at' => $dispatchedAt]);
            $this->stampJournal($reception, $dispatchedAt);

            if ($status === StockReceptionStatus::IN_TRANSIT) {
                continue;
            }

            $this->countIn($service, $reception->refresh(), $dispatchedAt);
        }
    }

    /**
     * The collector's visit to the shop.
     *
     * Most of what is declared gets loaded; sometimes the vendor had less on the
     * shelf than his slip claimed, which is the gap this whole leg exists to catch —
     * and the reason the depot is measured against the collected figure rather than
     * the declared one.
     */
    private function collectFor(StockReceptionService $service, StockReception $reception, Carbon $sentAt): Carbon
    {
        $collectedAt = $this->clamp($sentAt->copy()->addHours(random_int(3, 28)));
        $short = 0;

        $lines = $reception->items->map(function (StockReceptionItem $item) use (&$short): array {
            $declared = (int) $item->quantity_sent;
            $taken = random_int(1, 100) <= 15
                ? max(0, $declared - random_int(1, max(1, (int) floor($declared * 0.15))))
                : $declared;

            $short += $declared - $taken;

            return [
                'id' => $item->id,
                'quantity_collected' => $taken,
                'note' => $taken < $declared ? 'Quantité manquante en boutique au moment du ramassage.' : null,
            ];
        })->values()->all();

        $service->collect(
            reception: $reception,
            lines: $lines,
            actor: $this->collectorFor($reception),
            collectionNotes: $short > 0
                ? "Comptage contradictoire fait avec le vendeur : {$short} unité(s) manquante(s)."
                : 'Comptage conforme à la déclaration, colis scellé devant le vendeur.',
        );

        DB::table('stock_receptions')
            ->where('id', $reception->id)
            ->update(['collected_at' => $collectedAt]);

        $this->stampJournal($reception, $collectedAt);

        return $collectedAt;
    }

    /**
     * Count a shipment in at the depot.
     *
     * Most lines arrive complete; a minority is short or damaged, which is the
     * whole reason the received and rejected quantities are recorded separately
     * from the declared one.
     */
    private function countIn(StockReceptionService $service, StockReception $reception, Carbon $dispatchedAt): void
    {
        $receivedAt = $this->clamp($dispatchedAt->copy()->addDays(random_int(1, 3)));
        $lines = [];
        $rejectedTotal = 0;

        foreach ($reception->items as $item) {
            // Counted against what the collector loaded, not what the vendor
            // declared: a unit already missing at the shop must not be reported a
            // second time as lost on the road.
            $sent = $item->baselineQuantity();
            $roll = random_int(1, 100);

            [$received, $rejected] = match (true) {
                // Damaged in transit: the units arrived but cannot be sold.
                $roll <= 12 => [$sent - ($damaged = random_int(1, max(1, (int) floor($sent * 0.2)))), $damaged],
                // Missing on arrival: units left the shop but never reached the shelf.
                $roll <= 20 => [$sent - random_int(1, max(1, (int) floor($sent * 0.15))), 0],
                default => [$sent, 0],
            };

            $rejectedTotal += $rejected;

            $lines[] = [
                'id' => $item->id,
                'quantity_received' => max(0, $received),
                'quantity_rejected' => $rejected,
                'note' => $rejected > 0 ? 'Emballage abîmé, article écarté.' : null,
            ];
        }

        $ledgerFrom = $this->nextLedgerId();

        $service->validate(
            reception: $reception,
            lines: $lines,
            actor: $this->hubAgent,
            receptionNotes: $this->catalog->receptionNote($rejectedTotal),
            receivedAt: $receivedAt->toDateString(),
        );

        $this->stampSince('stock_adjustments', $ledgerFrom, $receivedAt);
        $this->stampJournal($reception, $receivedAt);
        $this->bump('units_received', (int) $reception->items()->sum('quantity_received'));
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    /**
     * Physical counts that disagreed with the screen.
     *
     * Each reason carries its own plausible direction: theft and damage remove
     * units, a counting error goes either way, a gift removes a handful. That
     * matters because the audit screen filters on the reason, and a dataset where
     * every motive produced the same delta would make the filter look pointless.
     *
     * @param  Collection<int, Product>  $products
     */
    private function seedInventoryCounts(User $vendor, $products): void
    {
        // Already counted once: replaying would stack corrections on a shelf that
        // has them, and `db:seed` is expected to be re-runnable. STOCK_PURGE=1
        // regenerates from scratch.
        if (StockAdjustment::query()->forSource(StockMovementSource::MANUAL)->exists()) {
            return;
        }

        $stocked = $products->fresh()->filter(fn (Product $product) => (int) $product->stock_quantity > 6);

        if ($stocked->isEmpty()) {
            return;
        }

        $ledger = app(StockLedgerService::class);

        $scenarios = [
            [StockAdjustmentReason::THEFT_OR_LOSS, -4, -1, 'Écart constaté lors du comptage hebdomadaire du rayon.'],
            [StockAdjustmentReason::DAMAGED, -3, -1, 'Articles cassés en manipulation, retirés du stock vendable.'],
            [StockAdjustmentReason::COUNT_ERROR, -2, 3, 'Correction après recomptage physique de l\'étagère.'],
            [StockAdjustmentReason::RETURN_NOT_RESTOCKED, -2, -1, 'Retour client non réintégré : produit ouvert.'],
            [StockAdjustmentReason::GIFT_OR_SAMPLE, -3, -1, 'Échantillons offerts à un revendeur.'],
        ];

        $dates = $this->moments(count($scenarios), ...self::PHASE_COUNTS);

        foreach ($scenarios as $index => [$reason, $minDelta, $maxDelta, $note]) {
            $product = $stocked->random();
            $recorded = (int) $product->fresh()->stock_quantity;

            $delta = random_int($minDelta, $maxDelta);

            // A count that confirms the screen is not a movement, and the ledger
            // refuses a negative shelf, so both are ruled out here.
            if ($delta === 0 || $recorded + $delta < 0) {
                continue;
            }

            $countedAt = $dates[$index] ?? $this->momentBetween(...self::PHASE_COUNTS);
            $ledgerFrom = $this->nextLedgerId();

            $ledger->setQuantity(
                product: $product,
                countedQuantity: $recorded + $delta,
                actor: $vendor,
                reason: $reason,
                note: $note,
            );

            $this->stampSince('stock_adjustments', $ledgerFrom, $countedAt);
            $this->bump('adjustments');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Orders picked from stock
    |--------------------------------------------------------------------------
    */

    /**
     * Orders composed from the catalog, which is what actually takes stock out.
     *
     * The amount is never invented: it is the sum of the lines at catalog prices
     * less an occasional global discount, computed by the same service the order
     * form uses, so a seeded order's total always matches its own item list.
     *
     * @param  Collection<int, City>  $cities
     */
    private function seedStockOrders(User $vendor, $cities, int $count): void
    {
        $dates = $this->moments($count, ...self::PHASE_ORDERS);

        for ($index = 0; $index < $count; $index++) {
            $pickable = $this->pickable();

            if ($pickable->isEmpty()) {
                break;
            }

            $items = $pickable->shuffle()
                ->take(random_int(1, min(3, $pickable->count())))
                ->map(fn (Product $product) => [
                    'product_id' => $product->id,
                    'quantity' => random_int(1, min(3, (int) $product->stock_quantity)),
                ])
                ->values()
                ->all();

            $this->placeOrder($vendor, $cities, $items, $dates[$index] ?? $this->momentBetween(...self::PHASE_ORDERS));
        }
    }

    /**
     * Two references sold down to their last units.
     *
     * Without this the whole catalog sits comfortably stocked and the low-stock
     * warning the pick-list and the catalog badges exist to raise would never
     * appear on a seeded shop. The shelf is emptied by a run of ordinary baskets
     * rather than one bulk order, because that is how a reference actually runs
     * out — and because a single 30 000 MAD cash-on-delivery parcel would poison
     * every average the dashboards compute.
     *
     * @param  Collection<int, City>  $cities
     */
    private function seedSellOuts(User $vendor, $cities): void
    {
        $baskets = [];
        $drained = [];

        for ($rank = 0; $rank < 2; $rank++) {
            // The thinnest shelf first: draining it takes the fewest baskets, so
            // the shortage costs the dataset two or three plausible orders.
            $target = $this->pickable($drained)->sortBy('stock_quantity')->first();

            if (! $target) {
                break;
            }

            $drained[] = $target->id;
            $remaining = (int) $target->stock_quantity - random_int(1, (int) config('stock.low_stock_threshold', 5));
            $planned = 0;

            while ($remaining > 0) {
                $baskets[] = [
                    'product_id' => $target->id,
                    'quantity' => $quantity = min($remaining, random_int(2, 5)),
                ];
                $remaining -= $quantity;
                $planned++;
            }

            if ($planned > 0) {
                $this->bump('sell_outs');
            }
        }

        // Both runs are planned before any order is placed, then interleaved and
        // walked in date order: two references selling out over the same two days
        // is two customer streams crossing, not one shelf emptied after the other.
        shuffle($baskets);
        $dates = $this->moments(count($baskets), ...self::PHASE_SELL_OUT);

        foreach ($baskets as $index => $line) {
            $this->placeOrder($vendor, $cities, [$line], $dates[$index] ?? $this->momentBetween(...self::PHASE_SELL_OUT));
        }
    }

    /**
     * Walk part of the queue across the picking bench.
     *
     * This is where the fulfilment flow becomes visible in the data. Packing an
     * order splits it in two, and which half it lands in is decided by geography
     * alone: a parcel whose depot already stands in the customer's city leaves
     * for delivery at once with a local courier, while one bound elsewhere stays
     * PREPARED and waits for a transfer next to the collected parcels.
     *
     * Both outcomes therefore appear on a seeded install without the seeder ever
     * choosing them — it presses the same button the depot agent presses.
     */
    private function seedPreparation(): void
    {
        $waiting = Order::query()
            ->where('status', OrderStatus::AWAITING_PREPARATION->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($waiting->isEmpty()) {
            return;
        }

        $preparation = app(OrderPreparationService::class);
        $packer = $this->resolvePacker();

        foreach ($waiting->take((int) ceil($waiting->count() * self::PACKED_SHARE)) as $order) {
            // Packed within a day or so of the order, which is the depot's own
            // service level rather than the moment this seeder happens to run.
            $packedAt = $this->clamp($order->created_at->copy()->addHours(random_int(3, 30)));

            $historyFrom = (int) DB::table('order_status_histories')->max('id') + 1;
            $changeFrom = (int) DB::table('order_change_histories')->max('id') + 1;

            $preparation->prepareByIds($packer, [$order->id]);

            $this->stampSince('order_status_histories', $historyFrom, $packedAt);
            $this->stampSince('order_change_histories', $changeFrom, $packedAt);

            $packed = $order->fresh();

            // The courier was handed the parcel when it was packed, not today.
            if ($packed->assigned_at) {
                DB::table('orders')->where('id', $packed->id)->update(['assigned_at' => $packedAt]);
            }

            $this->bump($packed->status === OrderStatus::PREPARED ? 'awaiting_transfer' : 'shipped_locally');
        }

        $this->bump('still_to_pack', $waiting->count() - (int) ceil($waiting->count() * self::PACKED_SHARE));
    }

    /**
     * True once the shop has sold from its catalog.
     *
     * Guards the order phase the way the catalog and shipment phases guard
     * themselves, so `php artisan db:seed` stays re-runnable instead of draining
     * the shelves a little further every time.
     */
    private function hasPickedOrders(): bool
    {
        return StockAdjustment::query()->forSource(StockMovementSource::ORDER)->exists();
    }

    /**
     * References the pick-list would offer: on the shelf, sellable, not blocked.
     *
     * @param  array<int, int>  $except
     * @return Collection<int, Product>
     */
    private function pickable(array $except = [])
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNull('blocked_at')
            ->where('stock_quantity', '>', 2)
            ->whereKeyNot($except)
            ->get();
    }

    /**
     * Place one order on the given lines.
     *
     * The amount is never invented: it is the sum of the lines at catalog prices
     * less an occasional global discount, computed by the same service the order
     * form uses, so a seeded order's total always matches its own item list.
     *
     * @param  Collection<int, City>  $cities
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    private function placeOrder(User $vendor, $cities, array $items, Carbon $createdAt): void
    {
        // A tenth of the baskets get a round discount, the way a seller rewards a
        // repeat customer.
        $discount = random_int(1, 100) <= 12 ? (float) $this->faker->pick([20, 30, 50]) : 0.0;
        $net = app(OrderStockService::class)->netAmount($items, $discount);

        if ($net <= 0) {
            return;
        }

        $city = $this->deliveryCity($cities);
        $customer = $this->faker->customer($city->name);
        $prepaid = random_int(1, 100) <= 15;

        $order = app(OrderService::class)->create([
            'customer_first_name' => $customer['first_name'],
            'customer_last_name' => $customer['last_name'],
            'customer_phone' => $customer['phone'],
            'customer_address' => $customer['address'],
            'city_id' => $city->id,
            'sector_id' => $city->sectors->random()->id,
            'payment_method' => $prepaid
                ? PaymentMethod::CARD_PAYMENT->value
                : PaymentMethod::CASH->value,
            // Cash on delivery collects the amount; a prepaid parcel only declares
            // its value. Mirrors NormalizesOrderPaymentAmounts.
            'order_amount' => $prepaid ? null : $net,
            'order_value' => $net,
            'discount_amount' => $discount,
            'notes' => $customer['notes'],
            'is_fragile' => $this->basketIsFragile($items),
            'items' => $items,
        ], $vendor);

        $this->stamp('orders', $order->id, $createdAt);
        $this->stampSince('order_status_histories', $order->statusHistories()->min('id'), $createdAt);
        $this->stampSince('stock_adjustments', $this->firstLedgerIdForOrder($order->id), $createdAt);

        $this->bump('orders');
        $this->bump('order_lines', count($items));
        $this->bump('units_sold', array_sum(array_column($items, 'quantity')));
    }

    /**
     * Where a basket is delivered.
     *
     * Weighted towards the depot's own city, so a seeded install shows both
     * outcomes of packing: a parcel that leaves for delivery on the spot, and one
     * that waits for an inter-city transfer.
     *
     * @param  Collection<int, City>  $cities
     */
    private function deliveryCity($cities): City
    {
        $depotId = Store::query()->whereKey(app(StoreContext::class)->id())->value('stock_hub_city_id');
        $home = $depotId ? $cities->firstWhere('id', (int) $depotId) : null;

        return $home && random_int(1, 100) <= self::HOME_CITY_SHARE
            ? $home
            : $cities->random();
    }

    /**
     * A basket is declared fragile as soon as one of its references is.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    private function basketIsFragile(array $items): bool
    {
        return Product::query()
            ->whereKey(array_column($items, 'product_id'))
            ->where('is_fragile', true)
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Store boundary & timestamps
    |--------------------------------------------------------------------------
    */

    /**
     * Run a generator as if the vendor had made the request himself.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withinStore(User $vendor, Store $store, callable $callback)
    {
        $context = app(StoreContext::class);
        $context->enforceFor($vendor, $store->id);

        try {
            return $callback();
        } finally {
            $context->reset();
        }
    }

    /**
     * A working moment between `$maxDaysAgo` and `$minDaysAgo` days back.
     */
    private function momentBetween(int $minDaysAgo, int $maxDaysAgo): Carbon
    {
        $daysAgo = random_int(min($minDaysAgo, $maxDaysAgo), max($minDaysAgo, $maxDaysAgo));

        return $this->clamp(
            $this->now->copy()
                ->subDays($daysAgo)
                ->setTime(random_int(8, 19), (int) $this->faker->pick([0, 10, 15, 25, 30, 45, 50]))
        );
    }

    /**
     * `$count` moments inside a slice of the window, oldest first.
     *
     * Callers walk this list in order so that a row's id and its date agree. On
     * an append-only ledger that is not cosmetic: an audit screen sorted by "most
     * recent first" would otherwise show a shuffled history, and nobody could
     * tell a seeding artefact from a real out-of-order movement.
     *
     * @return array<int, Carbon>
     */
    private function moments(int $count, int $fromDaysAgo, int $toDaysAgo): array
    {
        $moments = [];

        for ($index = 0; $index < max(0, $count); $index++) {
            $moments[] = $this->momentBetween($toDaysAgo, $fromDaysAgo);
        }

        usort($moments, static fn (Carbon $a, Carbon $b): int => $a <=> $b);

        return $moments;
    }

    private function clamp(Carbon $moment): Carbon
    {
        if ($moment->lessThan($this->windowStart)) {
            return $this->windowStart->copy()->addHours(random_int(8, 20));
        }

        if ($moment->greaterThan($this->now)) {
            return $this->now->copy()->subMinutes(random_int(15, 240));
        }

        return $moment;
    }

    /**
     * Backdate one row.
     *
     * Goes through the query builder rather than Eloquent because part of what is
     * stamped here is the stock ledger, whose model refuses every update by
     * design — and rightly so: nothing in the application may rewrite a movement.
     * Choosing when a demo movement happened is a property of the seeder, not of
     * the domain.
     */
    private function stamp(string $table, int $id, Carbon $at): void
    {
        DB::table($table)->where('id', $id)->update($this->timestampsFor($table, $at));
    }

    /**
     * Backdate the journal line the transition just appended.
     *
     * Each service call writes exactly one, so the newest row for the shipment is
     * the one that needs the date. Without this the timeline of a shipment dated
     * six weeks ago would claim every hand-over happened during the seeding run.
     */
    private function stampJournal(StockReception $reception, Carbon $at): void
    {
        $latest = DB::table('stock_reception_status_histories')
            ->where('stock_reception_id', $reception->id)
            ->max('id');

        if ($latest !== null) {
            $this->stamp('stock_reception_status_histories', (int) $latest, $at);
        }
    }

    /**
     * Backdate every row of a table from `$fromId` onwards.
     */
    private function stampSince(string $table, ?int $fromId, Carbon $at): void
    {
        if ($fromId === null) {
            return;
        }

        DB::table($table)->where('id', '>=', $fromId)->update($this->timestampsFor($table, $at));
    }

    /**
     * @return array<string, Carbon>
     */
    private function timestampsFor(string $table, Carbon $at): array
    {
        // The ledger and the shipment journal are append-only and carry no
        // updated_at column.
        return in_array($table, ['stock_adjustments', 'stock_reception_status_histories'], true)
            ? ['created_at' => $at]
            : ['created_at' => $at, 'updated_at' => $at];
    }

    /**
     * Id the next ledger row will take, so the movements a service is about to
     * write can be found again without the service having to return them.
     */
    private function nextLedgerId(): int
    {
        return (int) StockAdjustment::acrossStores()->max('id') + 1;
    }

    private function firstLedgerIdForOrder(int $orderId): ?int
    {
        return StockAdjustment::acrossStores()->where('order_id', $orderId)->min('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Reporting
    |--------------------------------------------------------------------------
    */

    private function bump(string $key, int $by = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $by;
    }

    private function receptionsIn(StockReceptionStatus $status): int
    {
        return StockReception::acrossStores()->where('status', $status->value)->count();
    }

    private function report(): void
    {
        $stats = $this->stats;

        $rows = [
            ['Vendeurs stock créés', $stats['vendors_created'] ?? 0],
            ['Boutiques créées', $stats['stores_created'] ?? 0],
            ['Membres d\'équipe (droits délégués)', $stats['team_members'] ?? 0],
            ['Produits au catalogue', $stats['products'] ?? 0],
            ['— dont bloqués par le dépôt', $stats['blocked_products'] ?? 0],
            ['Bordereaux de réception', $stats['receptions'] ?? 0],
            // Read from the table rather than counted as they were written: these
            // lines describe where the shipments stand now, and a shipment that has
            // moved on has left the stage it was counted at.
            ['— en attente de ramassage', $this->receptionsIn(StockReceptionStatus::AWAITING_PICKUP)],
            ['— ramassés, chez le ramasseur', $this->receptionsIn(StockReceptionStatus::COLLECTED)],
            ['— en route vers le dépôt', $this->receptionsIn(StockReceptionStatus::IN_TRANSIT)],
            ['— validés au dépôt', $this->receptionsIn(StockReceptionStatus::VALIDATED)],
            ['Unités réceptionnées', $stats['units_received'] ?? 0],
            ['Corrections d\'inventaire', $stats['adjustments'] ?? 0],
            ['Commandes depuis le stock', $stats['orders'] ?? 0],
            ['— en attente de préparation', $stats['still_to_pack'] ?? 0],
            ['— préparées, parties en livraison directe', $stats['shipped_locally'] ?? 0],
            ['— préparées, en attente de transfert', $stats['awaiting_transfer'] ?? 0],
            ['Références écoulées jusqu\'au stock faible', $stats['sell_outs'] ?? 0],
            ['Lignes de commande', $stats['order_lines'] ?? 0],
            ['Unités vendues', $stats['units_sold'] ?? 0],
            ['Mouvements au grand livre', StockAdjustment::acrossStores()->count()],
        ];

        $this->command?->newLine();
        $this->command?->table(['Jeu de données stock', 'Volume'], $rows);
        $this->command?->info('Comptes vendeurs : stock1@oowlmedia.com / stock2@oowlmedia.com (mot de passe : '.self::DEMO_PASSWORD.')');
        $this->command?->info('Équipe : magasinier@dar-argania.ma (inventaire) · comptoir@medina-tech.ma (commandes)');
    }
}
