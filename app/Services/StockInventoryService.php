<?php

namespace App\Services;

use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockInventoryCount;
use App\Models\User;
use App\Support\CountingContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockInventoryService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
    ) {}

    /**
     * Apply a reconciliation sheet.
     *
     * One transaction for the whole sheet: an inventory is a single statement
     * about a shelf at a point in time, and half of one applied is worse than
     * none — the seller would have no way of knowing which lines took effect.
     *
     * Two different things are written per line, and the distinction is the
     * point. The *ledger* only hears about counts that moved the stock, because
     * a movement that did not happen has no place among the ones that did. The
     * *count* is written every time, gap or no gap, with the machine and the
     * position it came from — so the product sheet can say when a reference was
     * last verified and by whom, which is precisely what a ledger of differences
     * can never tell you.
     *
     * @param  array<int, array{product_id: int, counted_quantity: int, reason: string|null, note: string|null}>  $lines
     * @return Collection<int, StockAdjustment>
     */
    public function apply(array $lines, User $actor, ?CountingContext $context = null): Collection
    {
        $context ??= new CountingContext;

        return DB::transaction(function () use ($lines, $actor, $context): Collection {
            $products = Product::query()
                ->whereKey(array_column($lines, 'product_id'))
                ->get()
                ->keyBy('id');

            $recorded = collect();

            foreach ($lines as $line) {
                $product = $products->get((int) $line['product_id']);

                if ($product === null) {
                    continue;
                }

                $countedQuantity = (int) $line['counted_quantity'];
                // Read before the ledger writes: afterwards the product carries
                // the counted figure and the gap is no longer visible.
                $stockBefore = (int) $product->stock_quantity;

                $adjustment = $this->ledger->setQuantity(
                    product: $product,
                    countedQuantity: $countedQuantity,
                    actor: $actor,
                    reason: StockAdjustmentReason::tryFrom((string) ($line['reason'] ?? '')),
                    note: $line['note'] ?? null,
                );

                $this->journalCount($product, $countedQuantity, $stockBefore, $actor, $adjustment, $context);

                if ($adjustment !== null) {
                    $recorded->push($adjustment);
                }
            }

            return $recorded;
        });
    }

    /**
     * Record the act of counting, whatever it found.
     *
     * store_id is copied from the product rather than left to the global scope:
     * a hub agent counting a vendor's shelf has no active store, and the scope
     * would leave the column null.
     */
    private function journalCount(
        Product $product,
        int $countedQuantity,
        int $stockBefore,
        User $actor,
        ?StockAdjustment $adjustment,
        CountingContext $context,
    ): StockInventoryCount {
        return StockInventoryCount::query()->create([
            'product_id' => $product->id,
            'store_id' => $product->store_id,
            'user_id' => $actor->id,
            'stock_adjustment_id' => $adjustment?->id,
            'counted_quantity' => $countedQuantity,
            'stock_before' => $stockBefore,
            'delta' => $countedQuantity - $stockBefore,
            ...$context->toAttributes(),
        ]);
    }

    /**
     * Headline figures of the current catalog, for the inventory screen.
     *
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        $threshold = (int) config('stock.low_stock_threshold', 5);

        $products = Product::query()
            ->active()
            ->get(['stock_quantity', 'unit_price', 'cost_price']);

        return [
            'products' => $products->count(),
            'units' => (int) $products->sum('stock_quantity'),
            'out_of_stock' => $products->where('stock_quantity', '<=', 0)->count(),
            'low_stock' => $products
                ->filter(fn (Product $p) => $p->stock_quantity > 0 && $p->stock_quantity <= $threshold)
                ->count(),
            // Valued at purchase cost where it is known, at the selling price
            // otherwise: a catalog with no costs would otherwise be worth zero.
            'stock_value' => round($products->sum(
                fn (Product $p) => (float) ($p->cost_price ?? $p->unit_price) * (int) $p->stock_quantity
            ), 2),
            'low_stock_threshold' => $threshold,
        ];
    }
}
