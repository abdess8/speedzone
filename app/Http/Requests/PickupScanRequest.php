<?php

namespace App\Http\Requests;

use App\Models\PickupRequest;
use Illuminate\Foundation\Http\FormRequest;

class PickupScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('scan', PickupRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:100'],
        ];
    }
}
