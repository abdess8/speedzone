<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The bridge between the catalog and an order.
 *
 * Prices are always re-read from the product row here, never taken from the
 * payload: the browser computes a total so the seller can see it while he types,
 * but the figure that reaches the database has to be one we own.
 */
class OrderStockService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
    ) {}

    /**
     * Load the products behind a line list, keyed by id.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     * @return Collection<int, Product>
     */
    public function resolveProducts(array $items): Collection
    {
        $ids = array_values(array_unique(array_map(
            static fn (array $item): int => (int) ($item['product_id'] ?? 0),
            $items
        )));

        return Product::query()->whereKey($ids)->get()->keyBy('id');
    }

    /**
     * Sum of the lines at catalog prices.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     */
    public function itemsTotal(array $items, ?Collection $products = null): float
    {
        $products ??= $this->resolveProducts($items);
        $total = 0.0;

        foreach ($items as $item) {
            $product = $products->get((int) ($item['product_id'] ?? 0));

            if ($product === null) {
                continue;
            }

            $total += OrderItem::computeLineTotal((float) $product->unit_price, (int) $item['quantity']);
        }

        return round($total, 2);
    }

    /**
     * Amount the order settles on: the lines, less the global discount.
     *
     * Clamped at zero rather than validated against the lines, so a discount
     * larger than the basket cannot turn into a negative amount the driver would
     * be asked to hand back.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     */
    public function netAmount(array $items, float $discount, ?Collection $products = null): float
    {
        return round(max(0, $this->itemsTotal($items, $products) - $discount), 2);
    }

    /**
     * Write the order lines and take the stock out of the catalog.
     *
     * Runs inside the order-creation transaction, so a line that cannot be
     * served rolls the whole order back instead of leaving a half-picked one.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     */
    public function attach(Order $order, array $items, User $actor): void
    {
        $products = $this->resolveProducts($items);

        foreach ($this->mergeDuplicates($items) as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'items' => __('stock.errors.unknown_product'),
                ]);
            }

            $this->assertPickable($product);

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => (float) $product->unit_price,
                'quantity' => $quantity,
                'line_total' => OrderItem::computeLineTotal((float) $product->unit_price, $quantity),
            ]);

            $this->ledger->debitForOrder($product, $quantity, $order, $actor);
        }
    }

    /**
     * Give the stock of a deleted order back to the catalog.
     */
    public function detach(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $items = $order->items()->with('product')->get();

            foreach ($items as $item) {
                if ($item->product instanceof Product) {
                    $this->ledger->restoreFromOrder($item->product, (int) $item->quantity, $order, $actor);
                }
            }

            $order->items()->delete();
        });
    }

    /**
     * Collapse repeated references into a single line.
     *
     * The pick-list prevents it, but the endpoint is reachable by anything
     * holding the seller's session, and two lines for the same product would
     * trip the unique index halfway through the loop.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     * @return array<int, int> product id → quantity
     */
    private function mergeDuplicates(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId === 0 || $quantity <= 0) {
                continue;
            }

            $merged[$productId] = ($merged[$productId] ?? 0) + $quantity;
        }

        return $merged;
    }

    private function assertPickable(Product $product): void
    {
        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'items' => __('stock.errors.inactive_product', ['product' => $product->name]),
            ]);
        }

        if ($product->is_blocked) {
            throw ValidationException::withMessages([
                'items' => __('stock.errors.blocked_product', ['product' => $product->name]),
            ]);
        }
    }
}
