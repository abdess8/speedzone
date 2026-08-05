<?php

namespace App\Http\Requests\Concerns;

use App\Models\Product;
use App\Services\OrderStockService;
use App\Support\StockPermissions;
use App\Support\StoreContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Catalog lines on an order form.
 *
 * The browser shows a running total while the seller picks, but the amount that
 * is stored is recomputed here from the product rows. Two reasons, and only the
 * second one is about attackers: a stale tab holding last week's price would
 * otherwise invoice last week's price.
 */
trait NormalizesOrderStockLines
{
    /**
     * @return array<int, array{product_id: int, quantity: int}>
     */
    protected function stockLines(): array
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return [];
        }

        $lines = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId > 0 && $quantity > 0) {
                $lines[] = ['product_id' => $productId, 'quantity' => $quantity];
            }
        }

        return $lines;
    }

    protected function hasStockLines(): bool
    {
        return $this->stockLines() !== [];
    }

    /**
     * Overwrite the declared amount with the sum of the lines.
     *
     * Called before {@see NormalizesOrderPaymentAmounts::mergeNormalizedPaymentAmounts()},
     * which then routes the figure to order_amount or order_value depending on
     * how the parcel is paid.
     */
    protected function mergeAmountFromStockLines(): void
    {
        $lines = $this->stockLines();

        if ($lines === []) {
            return;
        }

        $discount = (float) ($this->input('discount_amount') ?: 0);
        $net = app(OrderStockService::class)->netAmount($lines, $discount);

        $this->merge([
            'items' => $lines,
            'discount_amount' => $discount,
            'order_amount' => $net,
            'order_value' => $net,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function stockLineRules(): array
    {
        $storeId = app(StoreContext::class)->id();

        return [
            'items' => ['sometimes', 'array', 'max:100'],
            'items.*' => ['array'],
            'items.*.product_id' => [
                'required',
                'integer',
                // Scoped to the active shop and to sellable references: an id
                // from another vendor's catalog must not resolve at all.
                Rule::exists('products', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereNull('blocked_at')
                    ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId)),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * Reject a basket the shelf cannot serve, and a picker without the right.
     *
     * Runs after the field rules so the products are known to exist. The stock
     * test is repeated under a row lock at write time — this one exists to give
     * the seller a readable message on the line he has to fix, not to guarantee
     * anything about concurrency.
     */
    protected function validateStockAvailability(Validator $validator): void
    {
        $lines = $this->stockLines();

        if ($lines === [] || $validator->errors()->isNotEmpty()) {
            return;
        }

        if (! $this->user()?->hasPermission(StockPermissions::ORDERS_CREATE_WITH_STOCK)) {
            $validator->errors()->add('items', __('stock.errors.not_allowed'));

            return;
        }

        $products = app(OrderStockService::class)->resolveProducts($lines);

        foreach ($this->mergedQuantities($lines) as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product instanceof Product) {
                continue;
            }

            if ((int) $product->stock_quantity < $quantity) {
                $validator->errors()->add('items', __('stock.errors.insufficient', [
                    'product' => $product->name,
                    'available' => (int) $product->stock_quantity,
                    'requested' => $quantity,
                ]));
            }
        }
    }

    /**
     * Availability is checked per reference, not per line: the same product
     * listed twice draws from one shelf.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $lines
     * @return array<int, int>
     */
    private function mergedQuantities(array $lines): array
    {
        return (new Collection($lines))
            ->groupBy('product_id')
            ->map(fn (Collection $group): int => (int) $group->sum('quantity'))
            ->all();
    }
}
