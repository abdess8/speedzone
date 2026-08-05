<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PartnerOrderBulkScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('partners.deliveries.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1'],
            'orders.*' => ['string', 'max:100'],
        ];
    }
}
