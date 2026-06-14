<?php

use App\Enums\PaymentMethod;
use App\Http\Requests\StoreOrderRequest;
use Illuminate\Support\Facades\Validator;

test('store order request accepts card payment and cash', function () {
    $request = new StoreOrderRequest;

    foreach (PaymentMethod::values() as $method) {
        $validator = Validator::make(
            ['payment_method' => $method],
            ['payment_method' => $request->rules()['payment_method']]
        );

        expect($validator->passes())->toBeTrue();
    }
});

test('store order request rejects legacy cod payment method', function () {
    $request = new StoreOrderRequest;

    $validator = Validator::make(
        ['payment_method' => 'COD'],
        ['payment_method' => $request->rules()['payment_method']]
    );

    expect($validator->fails())->toBeTrue();
});

test('cash orders fail validation without order amount', function () {
    $request = new StoreOrderRequest;

    $validator = Validator::make(
        [
            'payment_method' => PaymentMethod::CASH->value,
            'order_amount' => null,
            'order_value' => null,
        ],
        [
            'payment_method' => $request->rules()['payment_method'],
            'order_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('order_amount'))->toBeTrue();
});

test('card payment orders accept nullable order value', function () {
    $request = new StoreOrderRequest;

    $validator = Validator::make(
        [
            'payment_method' => PaymentMethod::CARD_PAYMENT->value,
            'order_amount' => null,
            'order_value' => null,
        ],
        [
            'order_amount' => ['nullable'],
            'order_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]
    );

    expect($validator->passes())->toBeTrue();
});
