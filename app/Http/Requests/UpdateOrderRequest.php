<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
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

        if ($merge !== []) {
            $this->merge($merge);
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
            'order_amount' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'delivery_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_fragile' => ['sometimes', 'boolean'],
            'can_be_opened' => ['sometimes', 'boolean'],
        ];
    }
}
