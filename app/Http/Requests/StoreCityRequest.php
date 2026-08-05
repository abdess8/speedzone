<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCityRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('cities', 'name')->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('cities', 'code')->whereNull('deleted_at')],
            'region' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_stock_hub' => ['boolean'],
        ];
    }
}
