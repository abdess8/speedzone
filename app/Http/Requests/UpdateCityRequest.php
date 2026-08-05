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
        foreach (['is_active', 'is_stock_hub'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => $this->boolean($flag)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cityId = $this->route('city')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('cities', 'name')->ignore($cityId)->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('cities', 'code')->ignore($cityId)->whereNull('deleted_at')],
            'region' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_stock_hub' => ['sometimes', 'boolean'],
        ];
    }
}
