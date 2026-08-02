<?php

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\NormalizesOrderPaymentAmounts;
use Illuminate\Foundation\Http\FormRequest;

test('cash payment normalizes order value from order amount', function () {
    $request = new class extends FormRequest
    {
        use NormalizesOrderPaymentAmounts;

        public function exposeNormalize(): void
        {
            $this->mergeNormalizedPaymentAmounts();
        }
    };

    $request->merge([
        'payment_method' => PaymentMethod::CASH->value,
        'order_amount' => '500',
    ]);

    $request->exposeNormalize();

    expect($request->input('order_amount'))->toBe('500');
    expect($request->input('order_value'))->toBe('500');
});

test('card payment clears order amount and keeps optional order value', function () {
    $request = new class extends FormRequest
    {
        use NormalizesOrderPaymentAmounts;

        public function exposeNormalize(): void
        {
            $this->mergeNormalizedPaymentAmounts();
        }
    };

    $request->merge([
        'payment_method' => PaymentMethod::CARD_PAYMENT->value,
        'order_amount' => '500',
        'order_value' => '1000',
    ]);

    $request->exposeNormalize();

    expect($request->input('order_amount'))->toBeNull();
    expect($request->input('order_value'))->toBe('1000');
});

test('card payment treats empty order value as null', function () {
    $request = new class extends FormRequest
    {
        use NormalizesOrderPaymentAmounts;

        public function exposeNormalize(): void
        {
            $this->mergeNormalizedPaymentAmounts();
        }
    };

    $request->merge([
        'payment_method' => PaymentMethod::CARD_PAYMENT->value,
        'order_value' => '',
    ]);

    $request->exposeNormalize();

    expect($request->input('order_value'))->toBeNull();
});
