<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderTransitionService;
use App\Services\TrackingNumberGenerator;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $cities = City::query()
            ->where('is_active', true)
            ->whereHas('sectors', fn ($q) => $q->where('is_active', true))
            ->with(['sectors' => fn ($q) => $q->where('is_active', true)])
            ->get();

        if ($cities->isEmpty()) {
            $this->command?->warn('OrderSeeder skipped: no cities with active sectors found. Run CitySeeder & SectorSeeder first.');

            return;
        }

        $seller = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::SELLER))
            ->first()
            ?? User::query()->orderBy('id')->first();

        if (! $seller) {
            $this->command?->warn('OrderSeeder skipped: no users available to act as seller.');

            return;
        }

        $tracking = app(TrackingNumberGenerator::class);
        $transition = app(OrderTransitionService::class);

        $samples = [
            ['Youssef', 'Amrani', '0612345678', '12 Rue des Fleurs, Maarif', PaymentMethod::CARD_PAYMENT, null, 320.00, [OrderStatus::PICKUP_REQUESTED, OrderStatus::WAITING_PICKUP, OrderStatus::PICKED_UP]],
            ['Salma', 'Bennani', '0698765432', '45 Avenue Hassan II', PaymentMethod::CASH, 150.50, null, [OrderStatus::PICKUP_REQUESTED]],
            ['Karim', 'El Idrissi', '0655443322', 'Résidence Al Manar, Apt 8', PaymentMethod::CARD_PAYMENT, null, 540.00, [OrderStatus::PICKUP_REQUESTED, OrderStatus::WAITING_PICKUP, OrderStatus::PICKED_UP, OrderStatus::IN_DEPOT, OrderStatus::IN_TRANSIT]],
            ['Imane', 'Ouazzani', '0677889900', '3 Boulevard Zerktouni', PaymentMethod::CASH, 89.90, null, []],
        ];

        foreach ($samples as [$first, $last, $phone, $address, $payment, $orderAmount, $orderValue, $flow]) {
            $city = $cities->random();
            $sector = $city->sectors->random();

            $order = new Order([
                'customer_first_name' => $first,
                'customer_last_name' => $last,
                'customer_phone' => $phone,
                'customer_address' => $address,
                'city_id' => $city->id,
                'sector_id' => $sector->id,
                'payment_method' => $payment->value,
                'order_amount' => $orderAmount,
                'order_value' => $payment === PaymentMethod::CASH ? $orderAmount : $orderValue,
                'delivery_price' => (float) $sector->delivery_price,
                'notes' => 'Handle with care. Call before delivery.',
                'is_fragile' => (bool) random_int(0, 1),
                'can_be_opened' => (bool) random_int(0, 1),
            ]);
            $order->seller_id = $seller->id;
            $order->tracking_number = $tracking->generate();
            $order->status = OrderStatus::CREATED->value;
            $order->save();
            $order->recordStatus(OrderStatus::CREATED, $seller, 'Order created.');

            // Walk the order through its sample lifecycle (bypassing permission checks for the seed).
            $current = $order;
            foreach ($flow as $status) {
                $current->update(['status' => $status->value]);
                $current->recordStatus($status, $seller, null);
            }
        }
    }
}
