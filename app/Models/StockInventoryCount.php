<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One line of one inventory sheet: somebody looked at this shelf and said a number.
 *
 * Distinct from {@see StockAdjustment}, which only exists when the number
 * differed. Most counts confirm what the screen already said, and those are the
 * ones that answer "when was this last verified, and by whom" — a question the
 * ledger is structurally unable to answer because it never recorded them.
 *
 * Append-only, and enforced as such: a verification record a later bug can
 * rewrite proves nothing about the verification.
 */
class StockInventoryCount extends Model
{
    use BelongsToStore;
    use HasFactory;

    /** No updated_at: a row that is never updated has no business carrying one. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'store_id',
        'user_id',
        'stock_adjustment_id',
        'counted_quantity',
        'stock_before',
        'delta',
        'ip_address',
        'user_agent',
        'device_label',
        'latitude',
        'longitude',
        'location_accuracy_m',
    ];

    protected $casts = [
        'counted_quantity' => 'integer',
        'stock_before' => 'integer',
        'delta' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_accuracy_m' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Inventory counts are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Inventory counts are immutable and cannot be deleted.');
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

    /** Whoever held the shelf: the vendor, one of his team, or a hub agent. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The correction this count caused, when it caused one. */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /** Counts that agreed with the recorded quantity. */
    public function scopeConfirming(Builder $query): Builder
    {
        return $query->where('delta', 0);
    }

    public function scopeCorrecting(Builder $query): Builder
    {
        return $query->where('delta', '!=', 0);
    }

    /** Whether the browser volunteered a position for this count. */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
