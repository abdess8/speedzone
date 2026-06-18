<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends StorePartnerRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $partnerId = $this->route('partner')?->id;

        return array_merge($this->sharedRules(), [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('partners', 'name')->ignore($partnerId)],
            'sync_frequency_minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:1440'],
        ]);
    }
}
