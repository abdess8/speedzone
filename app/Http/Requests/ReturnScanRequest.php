<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->hasPermission('returns.update_status')
            || $this->user()?->hasPermission('returns.create')
            || $this->user()?->hasPermission('returns.manage')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scan' => ['required', 'string', 'max:500'],
            'comment' => ['nullable', 'string', 'max:2000'],
            // Only read on the hand-back step, where the parcel needs a name on
            // it; every other step ignores it.
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
