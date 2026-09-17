<?php

use App\Enums\ShopifyImportStatus;
use App\Services\Ecommerce\Shopify\ShopifyOrderMapper;
use App\Services\Ecommerce\YouCan\YouCanOrderMapper;

it('reads the customer from the Shopify shipping address', function () {
    $mapper = new ShopifyOrderMapper(new YouCanOrderMapper);
    $payload = [
        'id' => 1001,
        'name' => '#1001',
        'total_price' => '199.00',
        'fulfillment_status' => null,
        'financial_status' => 'pending',
        'shipping_address' => [
            'first_name' => 'Sara',
            'last_name' => 'Benali',
            'phone' => '0612345678',
            'city' => 'Casablanca',
            'address1' => '12 rue Maarif',
        ],
        'note_attributes' => [
            ['name' => 'Téléphone', 'value' => '0675068285'],
        ],
    ];

    $customer = (new ReflectionClass($mapper))->getMethod('customer');
    $customer->setAccessible(true);
    $parsed = $customer->invoke($mapper, $payload);

    expect($parsed['first_name'])->toBe('Sara')
        ->and($parsed['last_name'])->toBe('Benali')
        ->and($parsed['phone'])->toBe('0675068285')
        ->and($parsed['city'])->toBe('Casablanca')
        ->and($parsed['address'])->toBe('12 rue Maarif');
});

it('treats a null Shopify fulfillment_status as unfulfilled', function () {
    $mapper = new ShopifyOrderMapper(new YouCanOrderMapper);

    expect($mapper->matchesImportStatus([
        'fulfillment_status' => null,
        'financial_status' => 'paid',
        'status' => 'open',
    ], ShopifyImportStatus::Unfulfilled))->toBeTrue()
        ->and($mapper->matchesImportStatus([
            'fulfillment_status' => 'fulfilled',
            'status' => 'open',
        ], ShopifyImportStatus::Unfulfilled))->toBeFalse()
        ->and($mapper->matchesImportStatus([
            'financial_status' => 'paid',
            'fulfillment_status' => null,
        ], ShopifyImportStatus::Paid))->toBeTrue();
});

it('uses the Shopify order name as the shop-facing reference', function () {
    $mapper = new ShopifyOrderMapper(new YouCanOrderMapper);

    expect($mapper->orderRef(['name' => '#1001', 'order_number' => 1001]))->toBe('#1001')
        ->and($mapper->orderRef(['order_number' => 42]))->toBe('#42');
});
