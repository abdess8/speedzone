<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Services\OrderService;

test('clone payload copies customer delivery package and payment fields', function () {
    $order = new Order([
        'customer_first_name' => 'Youssef',
        'customer_last_name' => 'Amrani',
        'customer_phone' => '0612345678',
        'customer_address' => '12 Rue des Fleurs',
        'city_id' => 3,
        'sector_id' => 7,
        'is_fragile' => true,
        'can_be_opened' => false,
        'notes' => 'Handle with care',
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 500.00,
        'order_value' => 500.00,
        'delivery_price' => 35.00,
        'status' => OrderStatus::DELIVERED->value,
        'pickup_request_id' => 42,
        'tracking_number' => 'SPD-2026-123456',
    ]);

    $payload = app(OrderService::class)->clonePayload($order);

    expect($payload)->toMatchArray([
        'customer_first_name' => 'Youssef',
        'customer_last_name' => 'Amrani',
        'customer_phone' => '0612345678',
        'customer_address' => '12 Rue des Fleurs',
        'city_id' => 3,
        'sector_id' => 7,
        'is_fragile' => true,
        'can_be_opened' => false,
        'notes' => 'Handle with care',
        'payment_method' => 'CASH',
        'order_amount' => 500.0,
        'order_value' => 500.0,
        'delivery_price' => 35.0,
    ]);
});

test('clone payload does not copy lifecycle or assignment fields', function () {
    $order = new Order([
        'customer_first_name' => 'Test',
        'customer_last_name' => 'User',
        'customer_phone' => '0600000000',
        'customer_address' => 'Address',
        'city_id' => 1,
        'sector_id' => 2,
        'payment_method' => PaymentMethod::CARD_PAYMENT->value,
        'order_value' => 1000.00,
        'delivery_price' => 25.00,
        'status' => OrderStatus::IN_TRANSIT->value,
        'pickup_request_id' => 99,
        'tracking_number' => 'SPD-2026-999999',
    ]);

    $payload = app(OrderService::class)->clonePayload($order);

    expect($payload)->not->toHaveKeys([
        'status',
        'tracking_number',
        'pickup_request_id',
        'id',
        'seller_id',
    ]);
    expect($payload['order_amount'])->toBeNull();
    expect($payload['order_value'])->toBe(1000.0);
});

test('clone payload includes order amount only for cash orders', function () {
    $cash = new Order([
        'customer_first_name' => 'A',
        'customer_last_name' => 'B',
        'customer_phone' => '0600000000',
        'customer_address' => 'Addr',
        'city_id' => 1,
        'sector_id' => 2,
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => 150.50,
        'delivery_price' => 20.00,
    ]);

    $card = new Order([
        'customer_first_name' => 'A',
        'customer_last_name' => 'B',
        'customer_phone' => '0600000000',
        'customer_address' => 'Addr',
        'city_id' => 1,
        'sector_id' => 2,
        'payment_method' => PaymentMethod::CARD_PAYMENT->value,
        'order_amount' => null,
        'order_value' => 800.00,
        'delivery_price' => 20.00,
    ]);

    expect(app(OrderService::class)->clonePayload($cash)['order_amount'])->toBe(150.5);
    expect(app(OrderService::class)->clonePayload($card)['order_amount'])->toBeNull();
});
