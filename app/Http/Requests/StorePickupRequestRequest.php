<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePickupRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $seller = $this->user();
            $addresses = array_values(array_filter([
                $seller?->pickup_address_1,
                $seller?->pickup_address_2,
            ]));

            if ($addresses === []) {
                $validator->errors()->add('pickup_address', 'Configure your pickup addresses in your profile first.');

                return;
            }

            if (! in_array($this->input('pickup_address'), $addresses, true)) {
                $validator->errors()->add('pickup_address', 'Please select one of your configured pickup addresses.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $seller = $this->user();

        return [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('orders', 'id')
                    ->where('seller_id', $seller?->id)
                    ->where('status', 'CREATED')
                    ->whereNull('pickup_request_id'),
            ],
            'pickup_address' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_ids.required' => 'Select at least one order for pickup.',
            'order_ids.*.exists' => 'One or more selected orders are invalid or not eligible for pickup.',
            'pickup_address.in' => 'Please select one of your configured pickup addresses.',
        ];
    }
}
