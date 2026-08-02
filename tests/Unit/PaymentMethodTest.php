<?php

use App\Enums\PaymentMethod;

test('legacy cod values resolve to card payment', function () {
    expect(PaymentMethod::resolve('COD'))->toBe(PaymentMethod::CARD_PAYMENT);
    expect(PaymentMethod::resolve('CASH'))->toBe(PaymentMethod::CASH);
});

test('payment method values are card payment and cash', function () {
    expect(PaymentMethod::values())->toBe(['CARD_PAYMENT', 'CASH']);
});

test('payment method labels are human readable', function () {
    expect(PaymentMethod::CARD_PAYMENT->label())->toBe('Card Payment');
    expect(PaymentMethod::CASH->label())->toBe('Cash');
});

test('payment method display labels include emoji', function () {
    expect(PaymentMethod::CARD_PAYMENT->displayLabel())->toBe('💳 Card Payment');
    expect(PaymentMethod::CASH->displayLabel())->toBe('💵 Cash');
});

test('payment method options expose frontend metadata', function () {
    $options = PaymentMethod::options();

    expect($options)->toHaveCount(2);
    expect($options[0])->toMatchArray([
        'value' => 'CARD_PAYMENT',
        'label' => 'Card Payment',
        'icon' => 'ri-bank-card-fill',
        'emoji' => '💳',
        'color' => 'primary',
    ]);
    expect($options[1])->toMatchArray([
        'value' => 'CASH',
        'label' => 'Cash',
        'icon' => 'ri-money-dollar-box-fill',
        'emoji' => '💵',
        'color' => 'success',
    ]);
});

test('cash payment requires collection and card payment does not', function () {
    expect(PaymentMethod::CASH->requiresCashCollection())->toBeTrue();
    expect(PaymentMethod::CARD_PAYMENT->requiresCashCollection())->toBeFalse();
    expect(PaymentMethod::CASH->amountToCollect(500.0))->toBe(500.0);
    expect(PaymentMethod::CARD_PAYMENT->amountToCollect(500.0))->toBeNull();
});
