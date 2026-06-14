<?php

namespace App\Http\Requests;

use App\Enums\PickupRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PickupBulkStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('scan', \App\Models\PickupRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1'],
            'orders.*' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in([
                PickupRequestStatus::PICKED_UP->value,
                PickupRequestStatus::IN_DEPOT->value,
            ])],
        ];
    }
}
