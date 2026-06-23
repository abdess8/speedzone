<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transfer = $this->route('transfer');

        return $transfer && $this->user()?->can('scan', $transfer);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:255'],
        ];
    }
}
