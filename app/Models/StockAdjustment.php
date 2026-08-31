<?php

namespace App\Models;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementSource;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One line of the stock ledger: append-only, and enforced as such.
 *
 * The guard below is not defensive programming for its own sake. This table is
 * the only place that can answer "who took forty units out of this reference
 * and why", and an audit trail that a later bug can quietly rewrite is worth
 * nothing in the argument it exists to settle.
 */
class StockAdjustment extends Model
{
    use BelongsToStore;
    use HasFactory;

    /** No updated_at: a row that is never updated has no business carrying one. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'store_id',
        'user_id',
        'source',
        'reason',
        'note',
        'stock_before',
        'stock_after',
        'delta',
        'stock_reception_id',
        'order_id',
    ];

    protected $casts = [
        'source' => StockMovementSource::class,
        'reason' => StockAdjustmentReason::class,
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'delta' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Stock adjustments are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Stock adjustments are immutable and cannot be deleted.');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Whoever corrected the stock: the vendor, one of his team, or a hub agent.
     * Null for movements the system generated on its own.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(StockReception::class, 'stock_reception_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForSource(Builder $query, StockMovementSource|string $source): Builder
    {
        return $query->where('source', $source instanceof StockMovementSource ? $source->value : $source);
    }

    public function scopeForReason(Builder $query, StockAdjustmentReason|string $reason): Builder
    {
        return $query->where('reason', $reason instanceof StockAdjustmentReason ? $reason->value : $reason);
    }

    /** Movements that removed stock (sales, losses, downward corrections). */
    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('delta', '<', 0);
    }

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('delta', '>', 0);
    }
}
