<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnCustomerDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $return = $this->route('return');

        return $return && ($this->user()?->can('editCustomerData', $return) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'updated_customer_name' => ['nullable', 'string', 'max:255'],
            'updated_customer_phone' => ['nullable', 'string', 'max:50'],
            'updated_address' => ['nullable', 'string', 'max:1000'],
            'updated_city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ];
    }
}
