<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\NormalizesOrderPaymentAmounts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    use NormalizesOrderPaymentAmounts;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('is_fragile')) {
            $merge['is_fragile'] = $this->boolean('is_fragile');
        }

        if ($this->has('can_be_opened')) {
            $merge['can_be_opened'] = $this->boolean('can_be_opened');
        }

        if ($this->has('option_exchange')) {
            $merge['option_exchange'] = $this->boolean('option_exchange');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }

        if ($this->hasAny(['payment_method', 'order_amount', 'order_value'])) {
            $this->mergeNormalizedPaymentAmounts();
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sector_id.exists' => 'The selected sector does not belong to the chosen city.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $paymentRules = $this->hasAny(['payment_method', 'order_amount', 'order_value'])
            ? $this->paymentAmountRules()
            : [];

        foreach ($paymentRules as $field => $rules) {
            if ($field === 'order_amount') {
                $paymentRules[$field] = array_merge(['sometimes'], $rules);
            }
        }

        return [
            'customer_first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'customer_last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'required', 'string', 'max:50'],
            'customer_address' => ['sometimes', 'required', 'string', 'max:1000'],
            'city_id' => ['sometimes', 'required', 'integer', Rule::exists('cities', 'id')->whereNull('deleted_at')],
            'sector_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('sectors', 'id')
                    ->where('city_id', $this->input('city_id', $this->route('order')?->city_id))
                    ->whereNull('deleted_at'),
            ],
            'payment_method' => ['sometimes', 'required', Rule::in(PaymentMethod::values())],
            ...$paymentRules,
            'delivery_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_fragile' => ['sometimes', 'boolean'],
            'can_be_opened' => ['sometimes', 'boolean'],
            'option_exchange' => ['sometimes', 'boolean'],
        ];
    }
}
