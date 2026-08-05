<?php

namespace App\Http\Requests;

use App\Enums\PickupRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePickupStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(PickupRequestStatus::values())],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
