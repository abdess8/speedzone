<?php

namespace Database\Seeders;

use App\Enums\BillingFrequency;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PickupRequestStatus;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\SellerPaymentMethod;
use App\Enums\TransferStatus;
use App\Models\City;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\PickupRequest;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\InvoiceGeneratorService;
use App\Services\PickupReferenceGenerator;
use App\Services\ReturnReferenceGenerator;
use App\Services\TrackingNumberGenerator;
use App\Services\TransferService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Generates realistic, randomised Moroccan demo data across the whole platform:
 * orders, pickup requests, inter-city transfers, returns and seller invoices.
 *
 * Every record is pushed through its real business lifecycle (status history,
 * fee snapshots, transfer/return services) so the data respects the same rules
 * the application enforces in production.
 *
 * Run with:  php artisan db:seed --class=FakeMoroccanDataSeeder
 */
class FakeMoroccanDataSeeder extends Seeder
{
    /** @var array<int, string> */
    private array $firstNames = [
        'Mohamed', 'Youssef', 'Ahmed', 'Hamza', 'Omar', 'Ayoub', 'Yassine', 'Bilal',
        'Khalid', 'Anas', 'Reda', 'Mehdi', 'Othmane', 'Zakaria', 'Soufiane', 'Ismail',
        'Fatima', 'Khadija', 'Salma', 'Imane', 'Sara', 'Nadia', 'Hajar', 'Meryem',
        'Asmae', 'Hanane', 'Loubna', 'Ghita', 'Oumaima', 'Chaimae', 'Nada', 'Rim',
    ];

    /** @var array<int, string> */
    private array $lastNames = [
        'Alaoui', 'Bennani', 'El Amrani', 'Idrissi', 'Tazi', 'Cherkaoui', 'Bouazza',
        'El Fassi', 'Berrada', 'Lahlou', 'Sbai', 'Naciri', 'Saidi', 'Ouahbi',
        'Mansouri', 'Chraibi', 'Benjelloun', 'El Khattabi', 'Boukhris', 'Zniber',
        'Hilali', 'Daoudi', 'Sekkat', 'Bargach', 'El Ghazali', 'Smires',
    ];

    /** @var array<int, string> */
    private array $streetTemplates = [
        'Rue de Fès, Quartier Maarif',
        'Avenue Mohammed V, Centre Ville',
        'Boulevard Zerktouni, Gauthier',
        'Hay Riad, Secteur 12, Immeuble C',
        'Résidence Al Manar, Appartement 8',
        'Lotissement Ennassim, Villa 24',
        'Rue Ibn Battouta, Hay Hassani',
        'Avenue Hassan II, près de la gare',
        'Quartier Industriel, Sidi Bernoussi',
        'Hay Al Qods, Bloc 7, N° 142',
        'Rue Tarik Ibn Ziad, Agdal',
        'Boulevard Anfa, Racine',
        'Lotissement Riad Salam, N° 56',
        'Hay Mohammadi, Derb Loubila',
        'Avenue des FAR, Quartier Gueliz',
    ];

    /** @var array<int, string> */
    private array $banks = [
        'Attijariwafa Bank',
        'Banque Populaire',
        'Bank of Africa (BMCE)',
        'CIH Bank',
        'Crédit Agricole du Maroc',
        'Société Générale Maroc',
        'BMCI',
        'Al Barid Bank',
        'Crédit du Maroc',
    ];

    /** @var array<int, string> */
    private array $cinPrefixes = ['A', 'AB', 'B', 'BE', 'BH', 'BK', 'C', 'CD', 'D', 'EE', 'J', 'K', 'X'];

    /**
     * Order delivery lifecycle (after CREATED), used to walk an order forward.
     *
     * @var array<int, OrderStatus>
     */
    private array $chain = [
        OrderStatus::WAITING_PICKUP,
        OrderStatus::PICKED_UP,
        OrderStatus::IN_DEPOT,
        OrderStatus::IN_TRANSIT,
        OrderStatus::IN_DELIVERY_CITY,
        OrderStatus::OUT_FOR_DELIVERY,
    ];

    private TrackingNumberGenerator $tracking;

    private PickupReferenceGenerator $pickupRefs;

    private ReturnReferenceGenerator $returnRefs;

    private TransferService $transfers;

    private InvoiceGeneratorService $invoices;

    public function run(): void
    {
        $this->tracking = app(TrackingNumberGenerator::class);
        $this->pickupRefs = app(PickupReferenceGenerator::class);
        $this->returnRefs = app(ReturnReferenceGenerator::class);
        $this->transfers = app(TransferService::class);
        $this->invoices = app(InvoiceGeneratorService::class);

        $cities = City::query()
            ->where('is_active', true)
            ->whereHas('sectors', fn ($q) => $q->where('is_active', true))
            ->with(['sectors' => fn ($q) => $q->where('is_active', true)])
            ->get();

        if ($cities->count() < 2) {
            $this->command?->warn('FakeMoroccanDataSeeder skipped: need at least 2 active cities with sectors. Run CitySeeder & SectorSeeder first.');

            return;
        }

        $this->ensureSectorReturnPrices();
        $cities = $cities->fresh(['sectors' => fn ($q) => $q->where('is_active', true)]);

        $admin = User::query()->where('email', 'superadmin@speedzone.ma')->first()
            ?? User::query()->whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN))->first();

        $driver = User::query()->where('email', 'driver@speedzone.ma')->first()
            ?? User::query()->whereHas('roles', fn ($q) => $q->where('name', Role::DRIVER))->first();

        if (! $admin) {
            $this->command?->warn('FakeMoroccanDataSeeder skipped: no admin user found. Run DemoUsersSeeder first.');

            return;
        }

        $sellers = $this->resolveSellers($cities);

        $before = [
            'orders' => Order::query()->count(),
            'pickups' => PickupRequest::query()->count(),
            'returns' => OrderReturn::query()->count(),
        ];

        foreach ($sellers as $seller) {
            $this->seedForSeller($seller, $admin, $driver, $cities);
        }

        $this->randomiseInvoiceStatuses($admin);

        $this->command?->info('Fake Moroccan data generated:');
        $this->command?->info('  Sellers processed: '.$sellers->count());
        $this->command?->info('  Orders:            +'.(Order::query()->count() - $before['orders']));
        $this->command?->info('  Pickup requests:   +'.(PickupRequest::query()->count() - $before['pickups']));
        $this->command?->info('  Returns:           +'.(OrderReturn::query()->count() - $before['returns']));
        $this->command?->info('  Invoices total:    '.Invoice::query()->count());
    }

    /**
     * Make sure every active sector carries a sensible return fee so returned
     * orders contribute a non-zero charge on invoices.
     */
    private function ensureSectorReturnPrices(): void
    {
        Sector::query()
            ->where(fn ($q) => $q->whereNull('return_price')->orWhere('return_price', '<=', 0))
            ->get()
            ->each(function (Sector $sector): void {
                $delivery = (float) $sector->delivery_price;
                $base = $delivery > 0 ? $delivery * 0.6 : random_int(1500, 3500) / 100;
                $sector->forceFill(['return_price' => round($base, 2)])->save();
            });
    }

    /**
     * Resolve existing sellers and top up with fresh Moroccan sellers so we have
     * a varied pool spread across cities, all with complete billing profiles.
     *
     * @param  Collection<int, City>  $cities
     * @return Collection<int, User>
     */
    private function resolveSellers(Collection $cities): Collection
    {
        $sellerRole = Role::query()->where('name', Role::SELLER)->first();

        $sellers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::SELLER))
            ->get();

        // Create a few additional Moroccan sellers in varied cities.
        $newSellerCount = max(0, 6 - $sellers->count());
        $newSellerCount = max($newSellerCount, 4);

        $existingEmails = User::query()->pluck('email')->map(fn ($e) => strtolower($e))->all();

        for ($i = 0; $i < $newSellerCount; $i++) {
            $first = $this->pick($this->firstNames);
            $last = $this->pick($this->lastNames);
            $city = $cities->random();

            $slug = strtolower(preg_replace('/[^a-z]/i', '', $first.$last));
            $email = $slug.random_int(100, 999).'@boutique.ma';
            if (in_array(strtolower($email), $existingEmails, true)) {
                $email = $slug.random_int(1000, 9999).'@boutique.ma';
            }
            $existingEmails[] = strtolower($email);

            $seller = User::create([
                'name' => "{$first} {$last}",
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
                'role_id' => $sellerRole?->id,
                'city_id' => $city->id,
                'phone_number' => $this->phone(),
                'address' => $this->pick($this->streetTemplates).', '.$city->name,
                'pickup_address_1' => "Boutique {$first}, ".$city->name,
                'pickup_address_2' => "Dépôt {$last}, ".$city->name,
                'cin' => $this->cin(),
            ]);

            if ($sellerRole) {
                $seller->roles()->sync([$sellerRole->id]);
            }

            $sellers->push($seller);
        }

        // Give every seller a complete, randomised billing profile.
        $sellers->each(function (User $seller) use ($cities): void {
            if (! $seller->city_id) {
                $seller->city_id = $cities->random()->id;
            }

            $frequency = $this->pick([
                BillingFrequency::WEEKLY,
                BillingFrequency::BIWEEKLY,
                BillingFrequency::MONTHLY,
                BillingFrequency::MONTHLY,
            ]);

            $seller->forceFill([
                'billing_enabled' => true,
                'billing_frequency' => $frequency->value,
                'next_billing_date' => $frequency->nextDateFrom(Carbon::today())?->toDateString(),
                'payment_method' => $this->pick(SellerPaymentMethod::cases())->value,
                'bank_name' => $this->pick($this->banks),
                'rib' => $this->rib(),
            ])->save();
        });

        return $sellers->values();
    }

    /**
     * @param  Collection<int, City>  $cities
     */
    private function seedForSeller(User $seller, User $admin, ?User $driver, Collection $cities): void
    {
        $sellerCity = $cities->firstWhere('id', $seller->city_id) ?? $cities->random();
        $otherCities = $cities->where('id', '!=', $sellerCity->id)->values();

        // 1) A couple of brand-new orders sitting at CREATED.
        for ($i = 0; $i < random_int(2, 3); $i++) {
            $this->makeOrder($seller, $admin, $cities->random());
        }

        // 2) One pickup request at a random lifecycle stage.
        $this->makePickupRequest($seller, $admin, $driver, $cities, $sellerCity);

        // 3) Orders delivered directly (billable, paid to seller).
        for ($i = 0; $i < random_int(3, 5); $i++) {
            $order = $this->makeOrder($seller, $admin, $cities->random());
            $this->walkTo($order, OrderStatus::DELIVERED, $admin);
        }

        // 4) Failed deliveries (not billable on their own).
        for ($i = 0; $i < random_int(1, 2); $i++) {
            $order = $this->makeOrder($seller, $admin, $cities->random());
            $this->walkTo($order, OrderStatus::FAILED, $admin);
        }

        // 5) Returns: delivered/failed orders that come back to the seller.
        for ($i = 0; $i < random_int(2, 3); $i++) {
            $order = $this->makeOrder($seller, $admin, $cities->random());
            $this->walkTo($order, $this->pick([OrderStatus::DELIVERED, OrderStatus::FAILED]), $admin);
            // Most returns complete (order RETURNED -> billable); one stays mid-flow.
            $target = $i === 0 ? ReturnStatus::IN_TRANSIT_TO_DEPOT : ReturnStatus::DELIVERED_TO_VENDOR;
            $this->makeReturn($order, $admin, $target);
        }

        // 6) Inter-city transfer flow (requires a destination city != seller city).
        if ($otherCities->isNotEmpty()) {
            $this->makeTransferFlow($seller, $admin, $sellerCity, $otherCities->random());
        }

        // 7) Generate an invoice from everything billable so far for this seller.
        try {
            $this->invoices->generate($seller, null, null, $admin);
        } catch (Throwable $e) {
            $this->command?->warn("  Invoice generation skipped for {$seller->email}: {$e->getMessage()}");
        }

        // 8) A few more delivered/returned orders AFTER invoicing so the seller
        //    always has pending (un-invoiced) orders to settle next cycle.
        for ($i = 0; $i < random_int(2, 4); $i++) {
            $order = $this->makeOrder($seller, $admin, $cities->random());
            $this->walkTo($order, $this->pick([
                OrderStatus::DELIVERED,
                OrderStatus::DELIVERED,
                OrderStatus::OUT_FOR_DELIVERY,
            ]), $admin);
        }
    }

    /**
     * Create a single CREATED order with a random Moroccan customer.
     */
    private function makeOrder(User $seller, User $actor, City $city): Order
    {
        $sector = $city->sectors->random();
        // COD dominates Moroccan e-commerce; bias heavily to cash.
        $payment = random_int(1, 100) <= 80 ? PaymentMethod::CASH : PaymentMethod::CARD_PAYMENT;
        $orderAmount = round(random_int(8000, 65000) / 100, 2);

        $createdAt = Carbon::now()->subDays(random_int(1, 45))->subHours(random_int(0, 23));

        $order = Order::create([
            'tracking_number' => $this->tracking->generate(),
            'seller_id' => $seller->id,
            'customer_first_name' => $this->pick($this->firstNames),
            'customer_last_name' => $this->pick($this->lastNames),
            'customer_phone' => $this->phone(),
            'customer_address' => $this->pick($this->streetTemplates).', '.$city->name,
            'city_id' => $city->id,
            'sector_id' => $sector->id,
            'payment_method' => $payment->value,
            'order_amount' => $orderAmount,
            'order_value' => $payment === PaymentMethod::CASH ? $orderAmount : round(random_int(8000, 65000) / 100, 2),
            'delivery_price' => (float) $sector->delivery_price,
            'notes' => $this->pick([
                'Appeler avant la livraison.',
                'Livraison après 18h de préférence.',
                'Colis fragile, manipuler avec soin.',
                'Laisser chez le concierge si absent.',
                null,
            ]),
            'is_fragile' => (bool) random_int(0, 1),
            'can_be_opened' => (bool) random_int(0, 1),
            'status' => OrderStatus::CREATED->value,
        ]);

        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        $order->recordStatus(OrderStatus::CREATED, $seller, 'Commande créée.');

        return $order;
    }

    /**
     * Walk an order forward through its lifecycle to the target status.
     */
    private function walkTo(Order $order, OrderStatus $target, User $actor): void
    {
        $path = [];

        if ($target === OrderStatus::FAILED) {
            $path = array_merge($this->chain, [OrderStatus::FAILED]);
        } elseif ($target === OrderStatus::DELIVERED) {
            $path = array_merge($this->chain, [OrderStatus::DELIVERED]);
        } else {
            foreach ($this->chain as $status) {
                $path[] = $status;
                if ($status === $target) {
                    break;
                }
            }
        }

        foreach ($path as $status) {
            $order->update(['status' => $status->value]);
            $order->recordStatus($status, $actor, "Passage à {$status->label()}.");
        }
    }

    /**
     * @param  Collection<int, City>  $cities
     */
    private function makePickupRequest(User $seller, User $admin, ?User $driver, Collection $cities, City $sellerCity): void
    {
        $orders = collect(range(1, random_int(1, 3)))
            ->map(fn () => $this->makeOrder($seller, $admin, $cities->random()));

        $status = $this->pick([
            PickupRequestStatus::WAITING_FOR_PICKUP,
            PickupRequestStatus::PICKED_UP,
            PickupRequestStatus::IN_DEPOT,
            PickupRequestStatus::CANCELLED,
        ]);

        $pickup = PickupRequest::create([
            'reference' => $this->pickupRefs->generate(),
            'created_by' => $seller->id,
            'assigned_to' => $status === PickupRequestStatus::WAITING_FOR_PICKUP && random_int(0, 1) ? null : $driver?->id,
            'status' => PickupRequestStatus::WAITING_FOR_PICKUP->value,
            'pickup_address' => $seller->pickup_address_1 ?? ('Dépôt, '.$sellerCity->name),
            'number_of_packages' => $orders->count(),
            'total_orders_amount' => round((float) $orders->sum('order_amount'), 2),
            'notes' => $this->pick([
                'Ramassage du matin.',
                'Ramassage urgent.',
                'Colis prêts au dépôt.',
                null,
            ]),
        ]);

        $pickup->recordStatus(PickupRequestStatus::WAITING_FOR_PICKUP, $seller, null, 'Demande de ramassage créée.');

        foreach ($orders as $order) {
            $order->update([
                'pickup_request_id' => $pickup->id,
                'status' => OrderStatus::WAITING_PICKUP->value,
            ]);
            $order->recordStatus(OrderStatus::WAITING_PICKUP, $seller, "Ajoutée au ramassage {$pickup->reference}.");
        }

        if ($status === PickupRequestStatus::WAITING_FOR_PICKUP) {
            return;
        }

        if ($status === PickupRequestStatus::CANCELLED) {
            $pickup->update(['status' => PickupRequestStatus::CANCELLED->value]);
            $pickup->recordStatus(PickupRequestStatus::CANCELLED, $admin, PickupRequestStatus::WAITING_FOR_PICKUP->value, 'Ramassage annulé.');
            foreach ($pickup->orders as $order) {
                $order->update(['pickup_request_id' => null, 'status' => OrderStatus::CREATED->value]);
                $order->recordStatus(OrderStatus::CREATED, $admin, 'Ramassage annulé — commande libérée.');
            }

            return;
        }

        // PICKED_UP (and IN_DEPOT) — advance the pickup and its orders.
        $this->advancePickup($pickup, PickupRequestStatus::PICKED_UP, $admin);
        if ($status === PickupRequestStatus::IN_DEPOT) {
            $this->advancePickup($pickup, PickupRequestStatus::IN_DEPOT, $admin);
        }
    }

    private function advancePickup(PickupRequest $pickup, PickupRequestStatus $to, User $actor): void
    {
        $from = $pickup->status instanceof PickupRequestStatus ? $pickup->status->value : (string) $pickup->status;
        $pickup->update(['status' => $to->value]);
        $pickup->recordStatus($to, $actor, $from, "Ramassage : {$to->label()}.");

        $orderStatus = $to->orderStatus();
        if (! $orderStatus) {
            return;
        }

        foreach ($pickup->orders as $order) {
            $order->update(['status' => $orderStatus->value]);
            $order->recordStatus($orderStatus, $actor, "Ramassage {$pickup->reference} : {$to->label()}.");
        }
    }

    private function makeReturn(Order $order, User $admin, ReturnStatus $target): void
    {
        $reason = $this->pick(ReturnReason::cases())->value;

        $return = OrderReturn::create([
            'reference' => $this->returnRefs->generate(),
            'order_id' => $order->id,
            'created_by' => $admin->id,
            'initiated_by_role' => ReturnInitiatedByRole::ADMIN->value,
            'reason' => $reason,
            'status' => ReturnStatus::CREATED->value,
            'current_location_city_id' => $order->city_id,
            'return_address' => $order->customer_address,
            'return_notes' => 'Retour généré automatiquement (données de test).',
        ]);

        $return->recordStatus(ReturnStatus::CREATED, $admin, null, 'Retour créé.');
        $order->update(['return_id' => $return->id, 'is_returned' => false, 'status' => OrderStatus::RETURN_REQUESTED->value]);
        $order->recordStatus(OrderStatus::RETURN_REQUESTED, $admin, "Retour {$return->reference} créé.", returnId: $return->id);

        $flow = [
            ReturnStatus::RECEIVED_AT_HUB,
            ReturnStatus::IN_TRANSIT_TO_DEPOT,
            ReturnStatus::ARRIVED_VENDOR_HUB,
            ReturnStatus::IN_DELIVERY_TO_VENDOR,
            ReturnStatus::DELIVERED_TO_VENDOR,
        ];

        foreach ($flow as $status) {
            $this->advanceReturn($return, $order, $status, $admin);
            if ($status === $target) {
                break;
            }
        }
    }

    private function advanceReturn(OrderReturn $return, Order $order, ReturnStatus $to, User $admin): void
    {
        $from = $return->status instanceof ReturnStatus ? $return->status->value : (string) $return->status;
        $return->update(['status' => $to->value]);
        $return->recordStatus($to, $admin, $from, "Retour : {$to->label()}.");

        $orderStatus = match ($to) {
            ReturnStatus::RECEIVED_AT_HUB => OrderStatus::RETURN_IN_PROGRESS,
            ReturnStatus::DELIVERED_TO_VENDOR => OrderStatus::RETURNED,
            default => null,
        };

        if (! $orderStatus) {
            return;
        }

        $updates = ['status' => $orderStatus->value];
        if ($to === ReturnStatus::DELIVERED_TO_VENDOR) {
            $updates['is_returned'] = true;
        }

        $order->update($updates);
        $order->recordStatus($orderStatus, $admin, "Retour {$return->reference} : {$to->label()}.", returnId: $return->id);
    }

    /**
     * Build a real inter-city transfer (origin = seller city, destination = other
     * city) through TransferService, then advance it and deliver some packages.
     */
    private function makeTransferFlow(User $seller, User $admin, City $fromCity, City $toCity): void
    {
        $destSectors = $toCity->sectors;
        if ($destSectors->isEmpty()) {
            return;
        }

        // Orders must be IN_DEPOT, owned by a seller whose city = fromCity, and
        // delivered to toCity, to be eligible for this transfer.
        $orders = collect(range(1, random_int(2, 3)))->map(function () use ($seller, $admin, $toCity) {
            $order = $this->makeOrder($seller, $admin, $toCity);
            $this->walkTo($order, OrderStatus::IN_DEPOT, $admin);

            return $order->fresh();
        });

        try {
            $transfer = $this->transfers->create(
                $admin,
                $fromCity->id,
                $toCity->id,
                $orders->pluck('id')->all(),
                "Transfert {$fromCity->name} → {$toCity->name} (données de test).",
            );
        } catch (Throwable $e) {
            $this->command?->warn("  Transfer skipped ({$fromCity->name}->{$toCity->name}): {$e->getMessage()}");

            return;
        }

        $stage = $this->pick(['created', 'in_transit', 'received']);

        if ($stage === 'created') {
            return;
        }

        $this->transfers->applyStatus($transfer, TransferStatus::IN_TRANSIT, $admin, 'Départ du dépôt.');

        if ($stage === 'in_transit') {
            return;
        }

        $transfer = $this->transfers->applyStatus($transfer, TransferStatus::RECEIVED, $admin, 'Reçu à destination.');

        // Deliver about half of the received packages so they become billable.
        foreach ($transfer->orders as $index => $order) {
            if ($index % 2 === 0) {
                $this->advanceDeliveredFromDestination($order->fresh(), $admin);
            }
        }
    }

    /**
     * Push an order sitting at RECEIVED_IN_DESTINATION through to DELIVERED.
     */
    private function advanceDeliveredFromDestination(Order $order, User $admin): void
    {
        foreach ([OrderStatus::IN_DELIVERY_CITY, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED] as $status) {
            $order->update(['status' => $status->value]);
            $order->recordStatus($status, $admin, "Passage à {$status->label()}.");
        }
    }

    /**
     * Randomly move freshly generated invoices into a mix of statuses
     * (paid / sent / cancelled / left as generated).
     */
    private function randomiseInvoiceStatuses(User $admin): void
    {
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::GENERATED->value)
            ->get();

        foreach ($invoices as $invoice) {
            $roll = random_int(1, 100);

            try {
                if ($roll <= 40) {
                    $this->invoices->markPaid(
                        $invoice,
                        $admin,
                        Carbon::now()->subDays(random_int(1, 12)),
                        'invoices/receipts/seed-receipt-'.$invoice->id.'.pdf',
                    );
                } elseif ($roll <= 65) {
                    $this->invoices->markSent($invoice, $admin);
                } elseif ($roll <= 75) {
                    $this->invoices->cancel($invoice, $admin);
                }
                // else: leave as GENERATED
            } catch (Throwable $e) {
                $this->command?->warn("  Invoice {$invoice->invoice_number} status change skipped: {$e->getMessage()}");
            }
        }
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private function pick(array $items)
    {
        return $items[array_rand($items)];
    }

    private function phone(): string
    {
        return '0'.$this->pick(['6', '7']).str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    private function cin(): string
    {
        return $this->pick($this->cinPrefixes).random_int(100000, 999999);
    }

    private function rib(): string
    {
        $rib = '';
        for ($i = 0; $i < 24; $i++) {
            $rib .= random_int(0, 9);
        }

        return $rib;
    }
}
