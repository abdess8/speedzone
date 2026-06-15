<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectorRequest extends FormRequest
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
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->whereNull('deleted_at')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sectors', 'name')
                    ->where(fn ($query) => $query->where('city_id', $this->input('city_id')))
                    ->whereNull('deleted_at'),
            ],
            'delivery_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'return_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A sector with this name already exists in the selected city.',
        ];
    }
}
