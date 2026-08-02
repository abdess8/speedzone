<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignDriverSectorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sector_ids' => ['required', 'array', 'min:1'],
            'sector_ids.*' => ['integer', 'distinct', Rule::exists('sectors', 'id')->whereNull('deleted_at')],
            // When true the assignment list is replaced; otherwise sectors are appended.
            'replace' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function sectorIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('sector_ids', []))));
    }
}
