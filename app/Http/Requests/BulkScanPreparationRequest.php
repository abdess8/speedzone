<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The trolley: every label the agent swept before pressing the button.
 */
class BulkScanPreparationRequest extends FormRequest
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
            'orders' => ['required', 'array', 'min:1', 'max:500'],
            'orders.*' => ['string', 'max:100', 'distinct'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function trackingNumbers(): array
    {
        return array_map('strval', $this->input('orders', []));
    }
}
