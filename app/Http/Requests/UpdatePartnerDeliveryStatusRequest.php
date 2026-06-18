<?php

namespace App\Http\Requests;

use App\Enums\PartnerOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerDeliveryStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(PartnerOrderStatus::values())],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
