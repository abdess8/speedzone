<?php

namespace App\Http\Requests;

use App\Enums\DriverTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverTransactionRequest extends FormRequest
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
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            // Delivery payments are derived from delivered orders, never typed in.
            'transaction_type' => ['required', Rule::in(DriverTransactionType::manualValues())],
            // The sign is decided by the type, so the amount is always entered positive.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'driver_id' => __('driver_invoices.filters.driver'),
            'transaction_type' => __('driver_invoices.columns.type'),
            'amount' => __('driver_invoices.columns.amount'),
            'note' => __('driver_invoices.columns.note'),
        ];
    }
}
