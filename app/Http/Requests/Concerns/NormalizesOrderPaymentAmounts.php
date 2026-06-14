<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PaymentMethod;
use App\Models\Order;

trait NormalizesOrderPaymentAmounts
{
    protected function resolvedPaymentMethod(): PaymentMethod
    {
        if ($this->filled('payment_method')) {
            return PaymentMethod::resolve((string) $this->input('payment_method'));
        }

        $order = $this->route('order');

        if ($order instanceof Order) {
            return $order->payment_method instanceof PaymentMethod
                ? $order->payment_method
                : PaymentMethod::resolve((string) $order->payment_method);
        }

        return PaymentMethod::CASH;
    }

    protected function mergeNormalizedPaymentAmounts(): void
    {
        $method = $this->resolvedPaymentMethod();

        if ($method === PaymentMethod::CASH) {
            $this->merge([
                'order_amount' => $this->input('order_amount'),
                'order_value' => $this->input('order_amount'),
            ]);

            return;
        }

        $orderValue = $this->input('order_value');

        $this->merge([
            'order_amount' => null,
            'order_value' => ($orderValue !== null && $orderValue !== '') ? $orderValue : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentAmountRules(): array
    {
        $method = $this->resolvedPaymentMethod();

        if ($method === PaymentMethod::CASH) {
            return [
                'order_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
                'order_value' => ['nullable'],
            ];
        }

        return [
            'order_amount' => ['nullable'],
            'order_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
