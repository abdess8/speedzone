<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\NormalizesOrderPaymentAmounts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    use NormalizesOrderPaymentAmounts;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise booleans and defaults before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_method' => $this->input('payment_method', PaymentMethod::CASH->value),
            'is_fragile' => $this->boolean('is_fragile'),
            'can_be_opened' => $this->boolean('can_be_opened'),
        ]);

        $this->mergeNormalizedPaymentAmounts();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sector_id.exists' => 'The selected sector does not belong to the chosen city or is inactive.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_first_name' => ['required', 'string', 'max:255'],
            'customer_last_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_address' => ['required', 'string', 'max:1000'],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'sector_id' => [
                'required',
                'integer',
                Rule::exists('sectors', 'id')
                    ->where('is_active', true)
                    ->where('city_id', $this->input('city_id'))
                    ->whereNull('deleted_at'),
            ],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            ...$this->paymentAmountRules(),
            'delivery_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_fragile' => ['boolean'],
            'can_be_opened' => ['boolean'],
        ];
    }
}
