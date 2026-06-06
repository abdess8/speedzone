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
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
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
        ];
    }
}
