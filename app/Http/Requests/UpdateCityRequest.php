<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cityId = $this->route('city')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('cities', 'name')->ignore($cityId)],
            'region' => ['nullable', 'string', 'max:255'],
            'delivery_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
