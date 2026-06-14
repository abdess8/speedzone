<?php

namespace App\Http\Requests;

use App\Enums\PickupRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkScanPickupRequest extends FormRequest
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
            'tracking_numbers' => ['required', 'array', 'min:1'],
            'tracking_numbers.*' => ['required', 'string', 'max:100'],
            'to_status' => ['sometimes', Rule::in([PickupRequestStatus::PICKED_UP->value])],
        ];
    }
}
