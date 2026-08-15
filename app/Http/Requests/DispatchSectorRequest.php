<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchSectorRequest extends FormRequest
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
            'sector_id' => ['required', 'integer', Rule::exists('sectors', 'id')->whereNull('deleted_at')],
            'driver_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'reassign' => ['sometimes', 'boolean'],
        ];
    }
}
