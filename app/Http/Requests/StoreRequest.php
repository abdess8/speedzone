<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create and update payload for a vendor shop.
 *
 * A single class for both verbs: the only rule that differs is the uniqueness
 * check, which has to ignore the row being edited.
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_active', 'is_default'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => $this->boolean($flag)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $store = $this->route('store');
        $ownerId = $this->user()->accountOwnerId();

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('stores', 'name')
                    ->where(fn ($query) => $query->where('owner_id', $ownerId))
                    ->whereNull('deleted_at')
                    ->ignore($store?->id),
            ],
            'category' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            // Printed on this store's shipping labels, so it must stay legible
            // once scaled down to a thermal label.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'address' => ['nullable', 'string', 'max:255'],
            'pickup_address_1' => ['nullable', 'string', 'max:255'],
            'pickup_address_2' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }
}
