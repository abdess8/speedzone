<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PickupRequestStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\User;
use App\Services\OrderStatusService;

beforeEach(function () {
    $this->orderStatusService = app(OrderStatusService::class);
});

function autoTransitionCity(): City
{
    return City::query()->create([
        'name' => 'Same City',
        'code' => 'SMC',
        'region' => 'Test',
        'is_active' => true,
    ]);
}

function autoTransitionOrder(User $seller, City $city, OrderStatus $status, PickupRequest $pickup): Order
{
    $order = Order::query()->create([
        'tracking_number' => 'AT-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'seller_id' => $seller->id,
        'pickup_request_id' => $pickup->id,
        'customer_first_name' => 'Jane',
        'customer_last_name' => 'Doe',
        'customer_phone' => '0600000001',
        'customer_address' => '456 Test Ave',
        'city_id' => $city->id,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150,
        'delivery_price' => 25,
        'status' => $status->value,
    ]);

    $order->recordStatus($status, $seller, 'Test seed status.');

    return $order->fresh(['pickupRequest', 'seller.city', 'city']);
}

it('auto transitions to IN_DELIVERY_CITY when pickup is complete and cities match', function () {
    $city = autoTransitionCity();
    $seller = User::factory()->create(['city_id' => $city->id]);

    $pickup = PickupRequest::query()->create([
        'reference' => 'PU-2026-'.random_int(1000, 9999),
        'created_by' => $seller->id,
        'status' => PickupRequestStatus::IN_DEPOT->value,
        'pickup_address' => 'Depot A',
        'number_of_packages' => 1,
        'total_orders_amount' => 150,
    ]);

    $order = autoTransitionOrder($seller, $city, OrderStatus::IN_DEPOT, $pickup);

    $result = $this->orderStatusService->handleAutoCityDeliveryTransition($order);

    expect($result)->toBeTrue();
    expect($order->fresh()->status)->toBe(OrderStatus::IN_DELIVERY_CITY);

    $history = $order->statusHistories()->latest('id')->first();
    expect($history->status)->toBe(OrderStatus::IN_DELIVERY_CITY);
    expect($history->is_system)->toBeTrue();
    expect($history->user_id)->toBeNull();
});

it('does not auto transition when pickup and delivery cities differ', function () {
    $pickupCity = autoTransitionCity();
    $deliveryCity = City::query()->create([
        'name' => 'Other City',
        'code' => 'OTH',
        'region' => 'Test',
        'is_active' => true,
    ]);
    $seller = User::factory()->create(['city_id' => $pickupCity->id]);

    $pickup = PickupRequest::query()->create([
        'reference' => 'PU-2026-'.random_int(1000, 9999),
        'created_by' => $seller->id,
        'status' => PickupRequestStatus::IN_DEPOT->value,
        'pickup_address' => 'Depot B',
        'number_of_packages' => 1,
        'total_orders_amount' => 150,
    ]);

    $order = autoTransitionOrder($seller, $deliveryCity, OrderStatus::IN_DEPOT, $pickup);

    $result = $this->orderStatusService->handleAutoCityDeliveryTransition($order);

    expect($result)->toBeFalse();
    expect($order->fresh()->status)->toBe(OrderStatus::IN_DEPOT);
});

it('does not auto transition when pickup is not complete', function () {
    $city = autoTransitionCity();
    $seller = User::factory()->create(['city_id' => $city->id]);

    $pickup = PickupRequest::query()->create([
        'reference' => 'PU-2026-'.random_int(1000, 9999),
        'created_by' => $seller->id,
        'status' => PickupRequestStatus::PICKED_UP->value,
        'pickup_address' => 'Depot C',
        'number_of_packages' => 1,
        'total_orders_amount' => 150,
    ]);

    $order = autoTransitionOrder($seller, $city, OrderStatus::IN_DEPOT, $pickup);

    $result = $this->orderStatusService->handleAutoCityDeliveryTransition($order);

    expect($result)->toBeFalse();
    expect($order->fresh()->status)->toBe(OrderStatus::IN_DEPOT);
});

it('is idempotent and does not duplicate IN_DELIVERY_CITY transitions', function () {
    $city = autoTransitionCity();
    $seller = User::factory()->create(['city_id' => $city->id]);

    $pickup = PickupRequest::query()->create([
        'reference' => 'PU-2026-'.random_int(1000, 9999),
        'created_by' => $seller->id,
        'status' => PickupRequestStatus::IN_DEPOT->value,
        'pickup_address' => 'Depot D',
        'number_of_packages' => 1,
        'total_orders_amount' => 150,
    ]);

    $order = autoTransitionOrder($seller, $city, OrderStatus::IN_DELIVERY_CITY, $pickup);

    $result = $this->orderStatusService->handleAutoCityDeliveryTransition($order);

    expect($result)->toBeFalse();
    expect($order->statusHistories()->where('status', OrderStatus::IN_DELIVERY_CITY)->count())->toBe(1);
});
