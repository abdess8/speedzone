<?php

namespace App\Http\Requests;

use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Support\StoreContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mass inventory reconciliation.
 *
 * The client sends counted quantities, never deltas: the person holding the
 * shelf knows how many units are on it, and a payload of differences is one
 * double-submit away from applying twice.
 *
 * The mandatory-reason rule is enforced here rather than in the browser, because
 * a reason the seller can skip by opening dev tools is a reason his own audit
 * trail cannot rely on.
 */
class StoreStockAdjustmentsRequest extends FormRequest
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
        $storeId = app(StoreContext::class)->id();

        return [
            'adjustments' => ['required', 'array', 'min:1', 'max:500'],
            'adjustments.*' => ['array'],
            'adjustments.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')
                    ->whereNull('deleted_at')
                    ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId)),
            ],
            'adjustments.*.counted_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'adjustments.*.reason' => ['nullable', Rule::in(StockAdjustmentReason::values())],
            'adjustments.*.note' => ['nullable', 'string', 'max:500'],

            // Volunteered by the browser when the counter allows it. Never
            // required: a refused permission prompt must not block an inventory,
            // and a position the client can decline is corroboration rather
            // than proof either way.
            'location' => ['nullable', 'array'],
            'location.latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:location.longitude'],
            'location.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:location.latitude'],
            'location.accuracy' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Where the sheet says it was filled in from.
     *
     * @return array{latitude?: mixed, longitude?: mixed, accuracy?: mixed}
     */
    public function location(): array
    {
        return (array) ($this->validated()['location'] ?? []);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'adjustments.*.product_id.distinct' => __('stock.inventory.errors.duplicate_product'),
            'adjustments.*.product_id.exists' => __('stock.errors.unknown_product'),
        ];
    }

    /**
     * Require a motive on every line that actually moves the stock.
     *
     * The recorded quantities are read back from the database rather than trusted
     * from the payload, so a client cannot dodge the requirement by declaring a
     * stale "current" value that makes the delta look like zero.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $lines = (array) $this->input('adjustments', []);
            $recorded = Product::query()
                ->whereKey(array_column($lines, 'product_id'))
                ->pluck('stock_quantity', 'id');

            foreach ($lines as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);

                if (! $recorded->has($productId)) {
                    continue;
                }

                $delta = (int) $line['counted_quantity'] - (int) $recorded[$productId];

                if ($delta === 0) {
                    continue;
                }

                $reason = StockAdjustmentReason::tryFrom((string) ($line['reason'] ?? ''));

                if ($reason === null) {
                    $v->errors()->add("adjustments.{$index}.reason", __('stock.inventory.errors.reason_required'));

                    continue;
                }

                if ($reason->requiresNote() && trim((string) ($line['note'] ?? '')) === '') {
                    $v->errors()->add("adjustments.{$index}.note", __('stock.inventory.errors.note_required'));
                }
            }
        });
    }

    /**
     * @return array<int, array{product_id: int, counted_quantity: int, reason: string|null, note: string|null}>
     */
    public function lines(): array
    {
        return array_values($this->validated()['adjustments']);
    }
}
