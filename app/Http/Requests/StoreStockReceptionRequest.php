<?php

namespace App\Http\Requests;

use App\Enums\StockReceptionStatus;
use App\Models\City;
use App\Models\Store;
use App\Support\StoreContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Address the shipment to the shop's depot when the caller left it out.
     *
     * Once a shop warehouses somewhere there is nothing to choose — the form
     * shows the depot locked and an API client may simply omit the field. What
     * remains required is the very first shipment, which is where the vendor
     * genuinely picks a city.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('destination_city_id') !== null) {
            return;
        }

        $depotId = Store::query()
            ->whereKey(app(StoreContext::class)->id())
            ->value('stock_hub_city_id');

        if ($depotId !== null) {
            $this->merge(['destination_city_id' => (int) $depotId]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $storeId = app(StoreContext::class)->id();

        return [
            // Only these two are reachable from the form: a vendor either saves a
            // draft or asks us to come for the parcel. The rest of the lifecycle
            // belongs to the collector and the depot.
            'status' => ['nullable', Rule::in([
                StockReceptionStatus::DRAFT->value,
                StockReceptionStatus::AWAITING_PICKUP->value,
            ])],
            'destination_city_id' => [
                // A draft may still be undecided; requesting a pickup may not,
                // which the service checks when it freezes the declaration.
                $this->isBeingSent() ? 'required' : 'nullable',
                'integer',
                Rule::exists('cities', 'id')
                    ->where('is_stock_hub', true)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
                fn (string $attribute, mixed $value, Closure $fail) => $this->assertMatchesShopDepot($storeId, $value, $fail),
            ],
            'sent_at' => ['nullable', 'date'],
            'sending_notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['array'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')
                    ->whereNull('deleted_at')
                    ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId)),
            ],
            'items.*.quantity_sent' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('stock.receptions.errors.no_items'),
            'items.*.product_id.distinct' => __('stock.receptions.errors.duplicate_product'),
            'items.*.product_id.exists' => __('stock.errors.unknown_product'),
            'destination_city_id.required' => __('stock.receptions.errors.no_destination'),
            'destination_city_id.exists' => __('stock.receptions.errors.not_a_hub'),
        ];
    }

    private function isBeingSent(): bool
    {
        return $this->input('status') === StockReceptionStatus::AWAITING_PICKUP->value;
    }

    /**
     * A shop warehouses in one depot, so once it has one every later shipment
     * has to be addressed there.
     *
     * Without this a vendor could scatter his catalog across two cities while
     * `products.stock_quantity` kept reporting a single figure, and we would no
     * longer be able to say which city a prepared order ships out of.
     */
    private function assertMatchesShopDepot(?int $storeId, mixed $value, Closure $fail): void
    {
        if ($storeId === null || $value === null) {
            return;
        }

        $depotId = Store::query()->whereKey($storeId)->value('stock_hub_city_id');

        if ($depotId !== null && (int) $value !== (int) $depotId) {
            $fail(__('stock.receptions.errors.wrong_destination', [
                'city' => (string) City::query()->whereKey($depotId)->value('name'),
            ]));
        }
    }
}
