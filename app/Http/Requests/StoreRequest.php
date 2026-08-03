<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Store;
use Closure;
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
            // Only a city we actually warehouse in: the vendor's goods have to
            // land somewhere a hub agent can count them.
            'stock_hub_city_id' => [
                'nullable',
                'integer',
                Rule::exists('cities', 'id')
                    ->where('is_stock_hub', true)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
                fn (string $attribute, mixed $value, Closure $fail) => $this->assertDepotMovable($store, $value, $fail),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'pickup_address_1' => ['nullable', 'string', 'max:255'],
            'pickup_address_2' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * Refuse to move a shop's depot while goods are still on its shelves.
     *
     * The column is the only record of where the stock physically is, so
     * rewriting it would teleport every unit on hand to a warehouse that has
     * never seen them. Emptying the shop first — by selling out or by counting
     * the stock down — is the honest way through.
     */
    private function assertDepotMovable(?Store $store, mixed $value, Closure $fail): void
    {
        if ($store === null || (int) $value === (int) $store->stock_hub_city_id) {
            return;
        }

        $onHand = (int) Product::acrossStores()
            ->where('store_id', $store->id)
            ->sum('stock_quantity');

        if ($onHand > 0) {
            $fail(__('stores.errors.depot_not_empty', ['units' => $onHand]));
        }
    }
}
