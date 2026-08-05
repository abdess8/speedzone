<?php

namespace App\Http\Requests;

use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;

class EligibleTransferOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Transfer::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_city_id' => ['required', 'integer', 'exists:cities,id'],
            'to_city_id' => ['required', 'integer', 'exists:cities,id', 'different:from_city_id'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'customer' => ['nullable', 'string', 'max:255'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
        ];
    }
}
