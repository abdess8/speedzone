<?php

namespace App\Services;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementSource;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockReception;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of Product::stock_quantity.
 *
 * Every entry point funnels into {@see self::move()}, which re-reads the product
 * under a row lock, applies the delta and journals the movement inside one
 * transaction. Two consequences worth stating, because they are the reason this
 * class exists rather than a handful of `increment()` calls spread over the
 * controllers:
 *
 *  - the counter and the ledger are written together or not at all, so
 *    replaying stock_adjustments always reproduces stock_quantity;
 *  - the "is there enough left?" test happens after the lock, which is the only
 *    place where it means anything under concurrency.
 */
class StockLedgerService
{
    /**
     * Record an inventory count.
     *
     * The caller passes the quantity *counted on the shelf*, not a delta: that
     * is what the person holding the shelf knows, and computing the difference
     * here removes the class of bug where a UI sends "+3" twice.
     *
     * @param  StockAdjustmentReason|null  $reason  Required as soon as the count differs.
     * @return StockAdjustment|null Null when the count confirmed the recorded quantity.
     */
    public function setQuantity(
        Product $product,
        int $countedQuantity,
        User $actor,
        ?StockAdjustmentReason $reason = null,
        ?string $note = null,
    ): ?StockAdjustment {
        if ($countedQuantity < 0) {
            throw new InvalidArgumentException('A counted quantity cannot be negative.');
        }

        return DB::transaction(function () use ($product, $countedQuantity, $actor, $reason, $note): ?StockAdjustment {
            $locked = $this->lock($product);
            $delta = $countedQuantity - (int) $locked->stock_quantity;

            // A count that confirms the screen is not an event. Journalling it
            // would bury the movements that matter under rows saying "nothing
            // happened", and there is nothing to explain.
            if ($delta === 0) {
                return null;
            }

            if ($reason === null) {
                throw new InvalidArgumentException(
                    'A reason is required for a stock adjustment with a non-zero delta.'
                );
            }

            return $this->move(
                product: $locked,
                delta: $delta,
                source: StockMovementSource::MANUAL,
                actor: $actor,
                reason: $reason,
                note: $note,
            );
        });
    }

    /**
     * Credit the quantity a depot actually counted in from an inbound shipment.
     */
    public function creditFromReception(
        Product $product,
        int $quantity,
        StockReception $reception,
        ?User $actor = null,
        ?string $note = null,
    ): ?StockAdjustment {
        if ($quantity <= 0) {
            return null;
        }

        return DB::transaction(fn (): StockAdjustment => $this->move(
            product: $this->lock($product),
            delta: $quantity,
            source: StockMovementSource::RECEPTION,
            actor: $actor,
            note: $note,
            reception: $reception,
        ));
    }

    /**
     * Take stock out for an order line.
     *
     * @throws InsufficientStockException When the lock reveals fewer units than asked for.
     */
    public function debitForOrder(
        Product $product,
        int $quantity,
        Order $order,
        ?User $actor = null,
    ): StockAdjustment {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('An order line must debit at least one unit.');
        }

        return DB::transaction(function () use ($product, $quantity, $order, $actor): StockAdjustment {
            $locked = $this->lock($product);
            $available = (int) $locked->stock_quantity;

            if ($available < $quantity) {
                throw new InsufficientStockException($locked, $available, $quantity);
            }

            return $this->move(
                product: $locked,
                delta: -$quantity,
                source: StockMovementSource::ORDER,
                actor: $actor,
                order: $order,
            );
        });
    }

    /**
     * Give the units of a deleted order back to the catalog.
     *
     * Only wired to deletion. A returned parcel is *not* auto-restocked: what
     * comes back is often unsellable, so the vendor decides what re-enters the
     * catalog from the inventory screen — which is what the
     * RETURN_NOT_RESTOCKED reason exists to label.
     */
    public function restoreFromOrder(
        Product $product,
        int $quantity,
        Order $order,
        ?User $actor = null,
    ): ?StockAdjustment {
        if ($quantity <= 0) {
            return null;
        }

        return DB::transaction(fn (): StockAdjustment => $this->move(
            product: $this->lock($product),
            delta: $quantity,
            source: StockMovementSource::ORDER,
            actor: $actor,
            order: $order,
        ));
    }

    /**
     * Apply a delta to a locked product and journal it.
     *
     * Private: the public methods above exist so that every movement arrives
     * with the document that justifies it already attached.
     */
    private function move(
        Product $product,
        int $delta,
        StockMovementSource $source,
        ?User $actor,
        ?StockAdjustmentReason $reason = null,
        ?string $note = null,
        ?StockReception $reception = null,
        ?Order $order = null,
    ): StockAdjustment {
        $before = (int) $product->stock_quantity;
        $after = $before + $delta;

        $product->stock_quantity = $after;
        $product->save();

        // store_id is copied from the product rather than left to the global
        // scope: a hub agent validating a shipment has no active store, so the
        // scope would leave the column null.
        return StockAdjustment::query()->create([
            'product_id' => $product->id,
            'store_id' => $product->store_id,
            'user_id' => $actor?->id,
            'source' => $source->value,
            'reason' => $reason?->value,
            'note' => $note,
            'stock_before' => $before,
            'stock_after' => $after,
            'delta' => $delta,
            'stock_reception_id' => $reception?->id,
            'order_id' => $order?->id,
        ]);
    }

    /**
     * Re-read the product for update.
     *
     * The store boundary is lifted here on purpose: hub agents hold no active
     * store and still have to credit a vendor's shelf. Who may act on the row
     * was already settled by the policy that let the request in.
     */
    private function lock(Product $product): Product
    {
        return Product::acrossStores()
            ->whereKey($product->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
