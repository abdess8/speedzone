<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PickupRequestStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PickupRequest;
use App\Models\PickupStatusHistory;
use App\Models\Role;
use App\Models\User;
use App\Services\PickupReferenceGenerator;
use App\Services\TrackingNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoDataSeeder extends Seeder
{
    /** @var array<int, OrderStatus> */
    private array $deliveryChain = [
        OrderStatus::CREATED,
        OrderStatus::WAITING_PICKUP,
        OrderStatus::PICKED_UP,
        OrderStatus::IN_DEPOT,
        OrderStatus::IN_TRANSIT,
        OrderStatus::IN_DELIVERY_CITY,
        OrderStatus::OUT_FOR_DELIVERY,
    ];

    public function run(): void
    {
        $cities = City::query()
            ->where('is_active', true)
            ->whereHas('sectors', fn ($q) => $q->where('is_active', true))
            ->with(['sectors' => fn ($q) => $q->where('is_active', true)])
            ->get();

        if ($cities->isEmpty()) {
            $this->command?->warn('DemoDataSeeder skipped: run CitySeeder & SectorSeeder first.');

            return;
        }

        $driver = User::query()->where('email', 'driver@speedzone.ma')->first();
        $sellers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::SELLER))
            ->orderBy('email')
            ->get();

        if (! $driver || $sellers->count() < 5) {
            $this->command?->warn('DemoDataSeeder skipped: run DemoUsersSeeder first.');

            return;
        }

        // Replace previous demo orders/pickups so re-seeding stays idempotent.
        OrderStatusHistory::query()->delete();
        PickupStatusHistory::query()->delete();
        Order::query()->delete();
        PickupRequest::query()->delete();

        $tracking = app(TrackingNumberGenerator::class);
        $references = app(PickupReferenceGenerator::class);
        $superAdmin = User::query()->where('email', 'superadmin@speedzone.ma')->first() ?? $sellers->first();

        // Standalone orders (not linked to pickup requests) — 21 orders across delivery lifecycle.
        $standalonePlans = [
            [OrderStatus::CREATED, 3],
            [OrderStatus::PICKUP_REQUESTED, 2],
            [OrderStatus::IN_TRANSIT, 3],
            [OrderStatus::IN_DELIVERY_CITY, 3],
            [OrderStatus::OUT_FOR_DELIVERY, 3],
            [OrderStatus::DELIVERED, 3],
            [OrderStatus::FAILED, 2],
            [OrderStatus::RETURNED, 2],
        ];

        $orderIndex = 0;
        foreach ($standalonePlans as [$targetStatus, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $seller = $sellers[$orderIndex % $sellers->count()];
                $this->createOrderWithHistory(
                    seller: $seller,
                    actor: $superAdmin,
                    cities: $cities,
                    tracking: $tracking,
                    targetStatus: $targetStatus,
                    label: "Standalone #{$orderIndex}",
                );
                $orderIndex++;
            }
        }

        // Pickup request #1 — WAITING_FOR_PICKUP, assigned, 3 packages (driver scan testing).
        $pickup1Orders = collect(range(0, 2))->map(function (int $i) use ($sellers, $superAdmin, $cities, $tracking) {
            return $this->createOrderWithHistory(
                seller: $sellers[0],
                actor: $superAdmin,
                cities: $cities,
                tracking: $tracking,
                targetStatus: OrderStatus::CREATED,
                label: "Pickup-1 package {$i}",
            );
        });
        $this->createPickupRequest(
            seller: $sellers[0],
            actor: $superAdmin,
            driver: $driver,
            reference: $references->generate(),
            status: PickupRequestStatus::WAITING_FOR_PICKUP,
            orders: $pickup1Orders,
            address: $sellers[0]->pickup_address_1 ?? 'Casablanca Warehouse',
            notes: 'Morning pickup batch — 3 packages.',
        );

        // Pickup request #2 — WAITING_FOR_PICKUP, assigned, 2 packages.
        $pickup2Orders = collect(range(0, 1))->map(function (int $i) use ($sellers, $superAdmin, $cities, $tracking) {
            return $this->createOrderWithHistory(
                seller: $sellers[1],
                actor: $superAdmin,
                cities: $cities,
                tracking: $tracking,
                targetStatus: OrderStatus::CREATED,
                label: "Pickup-2 package {$i}",
            );
        });
        $this->createPickupRequest(
            seller: $sellers[1],
            actor: $superAdmin,
            driver: $driver,
            reference: $references->generate(),
            status: PickupRequestStatus::WAITING_FOR_PICKUP,
            orders: $pickup2Orders,
            address: $sellers[1]->pickup_address_1 ?? 'Casablanca Store',
            notes: 'Afternoon pickup — 2 packages.',
        );

        // Pickup request #3 — PICKED_UP, 3 packages.
        $pickup3Orders = collect(range(0, 2))->map(function (int $i) use ($sellers, $superAdmin, $cities, $tracking) {
            return $this->createOrderWithHistory(
                seller: $sellers[2],
                actor: $superAdmin,
                cities: $cities,
                tracking: $tracking,
                targetStatus: OrderStatus::CREATED,
                label: "Pickup-3 package {$i}",
            );
        });
        $this->createPickupRequest(
            seller: $sellers[2],
            actor: $superAdmin,
            driver: $driver,
            reference: $references->generate(),
            status: PickupRequestStatus::PICKED_UP,
            orders: $pickup3Orders,
            address: $sellers[2]->pickup_address_1 ?? 'Fès Store',
            notes: 'Already picked up by driver.',
        );

        // Pickup request #4 — IN_DEPOT, 3 packages.
        $pickup4Orders = collect(range(0, 2))->map(function (int $i) use ($sellers, $superAdmin, $cities, $tracking) {
            return $this->createOrderWithHistory(
                seller: $sellers[3],
                actor: $superAdmin,
                cities: $cities,
                tracking: $tracking,
                targetStatus: OrderStatus::CREATED,
                label: "Pickup-4 package {$i}",
            );
        });
        $this->createPickupRequest(
            seller: $sellers[3],
            actor: $superAdmin,
            driver: $driver,
            reference: $references->generate(),
            status: PickupRequestStatus::IN_DEPOT,
            orders: $pickup4Orders,
            address: $sellers[3]->pickup_address_1 ?? 'Rabat Store',
            notes: 'Received at central depot.',
        );

        // Pickup request #5 — CANCELLED (orders released back to CREATED).
        $pickup5Orders = collect(range(0, 1))->map(function (int $i) use ($sellers, $superAdmin, $cities, $tracking) {
            return $this->createOrderWithHistory(
                seller: $sellers[4],
                actor: $superAdmin,
                cities: $cities,
                tracking: $tracking,
                targetStatus: OrderStatus::CREATED,
                label: "Pickup-5 package {$i}",
            );
        });
        $this->createPickupRequest(
            seller: $sellers[4],
            actor: $superAdmin,
            driver: $driver,
            reference: $references->generate(),
            status: PickupRequestStatus::CANCELLED,
            orders: $pickup5Orders,
            address: $sellers[4]->pickup_address_1 ?? 'Marrakech Store',
            notes: 'Cancelled by seller before pickup.',
        );

        // Pickup request #6 — WAITING_FOR_PICKUP, unassigned, 1 package.
        $pickup6Orders = collect([
            $this->createOrderWithHistory(
                seller: $sellers[0],
                actor: $superAdmin,
                cities: $cities,
                tracking: $tracking,
                targetStatus: OrderStatus::CREATED,
                label: 'Pickup-6 single package',
            ),
        ]);
        $this->createPickupRequest(
            seller: $sellers[0],
            actor: $superAdmin,
            driver: null,
            reference: $references->generate(),
            status: PickupRequestStatus::WAITING_FOR_PICKUP,
            orders: $pickup6Orders,
            address: $sellers[0]->pickup_address_2 ?? 'Mohammedia Warehouse',
            notes: 'Awaiting driver assignment.',
        );

        $orderCount = Order::query()->count();
        $pickupCount = PickupRequest::query()->count();

        $this->command?->info("Demo data seeded: {$orderCount} orders, {$pickupCount} pickup requests.");
    }

    /**
     * @param  Collection<int, City>  $cities
     */
    private function createOrderWithHistory(
        User $seller,
        User $actor,
        Collection $cities,
        TrackingNumberGenerator $tracking,
        OrderStatus $targetStatus,
        string $label,
    ): Order {
        $city = $cities->random();
        $sector = $city->sectors->random();
        $payment = random_int(0, 1) ? PaymentMethod::CASH : PaymentMethod::CARD_PAYMENT;
        $orderAmount = $payment === PaymentMethod::CASH ? round(random_int(5000, 25000) / 100, 2) : null;
        $orderValue = $payment === PaymentMethod::CARD_PAYMENT ? round(random_int(5000, 50000) / 100, 2) : null;

        $order = Order::create([
            'tracking_number' => $tracking->generate(),
            'seller_id' => $seller->id,
            'customer_first_name' => 'Client',
            'customer_last_name' => substr(md5($label), 0, 6),
            'customer_phone' => '06'.random_int(10000000, 99999999),
            'customer_address' => 'Adresse client, '.$city->name,
            'city_id' => $city->id,
            'sector_id' => $sector->id,
            'payment_method' => $payment->value,
            'order_amount' => $orderAmount,
            'order_value' => $payment === PaymentMethod::CASH ? $orderAmount : $orderValue,
            'delivery_price' => (float) $sector->delivery_price,
            'notes' => $label,
            'is_fragile' => (bool) random_int(0, 1),
            'can_be_opened' => (bool) random_int(0, 1),
            'status' => OrderStatus::CREATED->value,
        ]);

        foreach ($this->statusPathTo($targetStatus) as $status) {
            $order->update(['status' => $status->value]);
            $order->recordStatus($status, $actor, "Seeded transition to {$status->label()}.");
        }

        return $order->fresh();
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function createPickupRequest(
        User $seller,
        User $actor,
        ?User $driver,
        string $reference,
        PickupRequestStatus $status,
        Collection $orders,
        string $address,
        ?string $notes = null,
    ): PickupRequest {
        $totalAmount = round((float) $orders->sum('order_amount'), 2);

        $pickup = PickupRequest::create([
            'reference' => $reference,
            'created_by' => $seller->id,
            'assigned_to' => $driver?->id,
            'status' => PickupRequestStatus::WAITING_FOR_PICKUP->value,
            'pickup_address' => $address,
            'number_of_packages' => $orders->count(),
            'total_orders_amount' => $totalAmount,
            'notes' => $notes,
        ]);

        $pickup->recordStatus(
            PickupRequestStatus::WAITING_FOR_PICKUP,
            $seller,
            null,
            'Pickup request created.'
        );

        if ($driver) {
            $pickup->recordStatus(
                PickupRequestStatus::WAITING_FOR_PICKUP,
                $actor,
                PickupRequestStatus::WAITING_FOR_PICKUP->value,
                "Assigned to {$driver->full_name}."
            );
        }

        foreach ($orders as $order) {
            $order->update([
                'pickup_request_id' => $pickup->id,
                'status' => OrderStatus::WAITING_PICKUP->value,
            ]);
            $order->recordStatus(OrderStatus::WAITING_PICKUP, $seller, "Added to pickup {$reference}.");
        }

        if ($status === PickupRequestStatus::WAITING_FOR_PICKUP) {
            return $pickup->fresh(['orders']);
        }

        if ($status === PickupRequestStatus::PICKED_UP) {
            $this->advancePickup($pickup, PickupRequestStatus::PICKED_UP, $actor);

            return $pickup->fresh(['orders']);
        }

        if ($status === PickupRequestStatus::IN_DEPOT) {
            $this->advancePickup($pickup, PickupRequestStatus::PICKED_UP, $actor);
            $this->advancePickup($pickup, PickupRequestStatus::IN_DEPOT, $actor);

            return $pickup->fresh(['orders']);
        }

        // CANCELLED — release orders back to CREATED.
        $from = PickupRequestStatus::WAITING_FOR_PICKUP->value;
        $pickup->update(['status' => PickupRequestStatus::CANCELLED->value]);
        $pickup->recordStatus(PickupRequestStatus::CANCELLED, $actor, $from, 'Pickup request cancelled.');

        foreach ($pickup->orders as $order) {
            $order->update([
                'pickup_request_id' => null,
                'status' => OrderStatus::CREATED->value,
            ]);
            $order->recordStatus(OrderStatus::CREATED, $actor, 'Pickup request cancelled — order released.');
        }

        return $pickup->fresh(['orders']);
    }

    private function advancePickup(PickupRequest $pickup, PickupRequestStatus $toStatus, User $actor): void
    {
        $from = $pickup->status instanceof PickupRequestStatus
            ? $pickup->status->value
            : (string) $pickup->status;

        $orderStatus = $toStatus->orderStatus();

        $pickup->update(['status' => $toStatus->value]);
        $pickup->recordStatus($toStatus, $actor, $from, "Pickup moved to {$toStatus->label()}.");

        if ($orderStatus === null) {
            return;
        }

        foreach ($pickup->orders as $order) {
            $order->update(['status' => $orderStatus->value]);
            $order->recordStatus($orderStatus, $actor, "Pickup {$pickup->reference}: {$toStatus->label()}.");
        }
    }

    /**
     * Build a realistic status history path ending at the target status.
     *
     * @return array<int, OrderStatus>
     */
    private function statusPathTo(OrderStatus $target): array
    {
        if ($target === OrderStatus::PICKUP_REQUESTED) {
            return [OrderStatus::CREATED, OrderStatus::PICKUP_REQUESTED];
        }

        if ($target === OrderStatus::FAILED) {
            return array_merge($this->prefixThrough(OrderStatus::OUT_FOR_DELIVERY), [OrderStatus::FAILED]);
        }

        if ($target === OrderStatus::RETURNED) {
            return array_merge($this->prefixThrough(OrderStatus::OUT_FOR_DELIVERY), [OrderStatus::RETURNED]);
        }

        if ($target === OrderStatus::DELIVERED) {
            return array_merge($this->prefixThrough(OrderStatus::OUT_FOR_DELIVERY), [OrderStatus::DELIVERED]);
        }

        return $this->prefixThrough($target);
    }

    /**
     * @return array<int, OrderStatus>
     */
    private function prefixThrough(OrderStatus $target): array
    {
        $path = [];
        $terminalExtras = [OrderStatus::DELIVERED, OrderStatus::FAILED, OrderStatus::RETURNED];

        foreach ($this->deliveryChain as $status) {
            $path[] = $status;
            if ($status === $target) {
                return $path;
            }
        }

        if (in_array($target, $terminalExtras, true)) {
            return array_merge($this->deliveryChain, [$target]);
        }

        return [OrderStatus::CREATED];
    }
}
