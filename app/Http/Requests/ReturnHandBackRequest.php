<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnHandBackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->hasPermission('returns.manage')
            || $user?->hasPermission('returns.update_status')
            || $user?->hasPermission('returns.transition.to_in_delivery_to_vendor')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.reference' => ['required_without:items.*.id', 'nullable', 'string', 'max:100'],
            'items.*.id' => ['required_without:items.*.reference', 'nullable', 'integer'],
            'items.*.driver_id' => ['required', 'integer', 'exists:users,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
