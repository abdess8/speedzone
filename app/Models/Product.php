<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sellable reference in a vendor's catalog, stored in one of our depots.
 *
 * `stock_quantity` is the availability the pick-list reads. It is never written
 * directly: every change goes through StockLedgerService, which locks the row
 * and journals the movement in the same transaction.
 */
class Product extends Model
{
    use BelongsToStore;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'seller_id',
        'store_id',
        'name',
        'sku',
        'barcode',
        'category',
        'description',
        'photo_path',
        'unit_price',
        'cost_price',
        'is_fragile',
        'weight_grams',
        'length_cm',
        'width_cm',
        'height_cm',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_fragile' => 'boolean',
        'is_active' => 'boolean',
        'weight_grams' => 'integer',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'stock_quantity' => 'integer',
        'blocked_at' => 'datetime',
    ];

    protected $appends = [
        'photo_url',
        'initials',
        'is_blocked',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ProductHistory::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Every time somebody counted this reference, including the times they found
     * exactly what the screen said. The ledger above only holds the other kind.
     */
    public function inventoryCounts(): HasMany
    {
        return $this->hasMany(StockInventoryCount::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function receptionItems(): HasMany
    {
        return $this->hasMany(StockReceptionItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? User::publicStorageUrl($this->photo_path) : null;
    }

    /**
     * Fallback avatar for a product shipped without a photo.
     *
     * A catalog of three hundred identical grey squares is unreadable, so a
     * product with no image gets its initials on a coloured tile instead.
     */
    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = array_map(
            static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice(array_filter($words), 0, 2)
        );

        return $letters === [] ? '#' : implode('', $letters);
    }

    public function getIsBlockedAttribute(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Gross margin per unit, or null when the vendor does not track his costs.
     */
    public function margin(): ?float
    {
        if ($this->cost_price === null) {
            return null;
        }

        return round((float) $this->unit_price - (float) $this->cost_price, 2);
    }

    /**
     * Whether this reference may be picked into a new order.
     */
    public function isPickable(): bool
    {
        return $this->is_active && ! $this->is_blocked && $this->stock_quantity > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeOwnedBy(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->whereNotNull('blocked_at');
    }

    /**
     * Free-text search over the three things a picker actually knows: what the
     * product is called, its reference, and the code under the barcode.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('barcode', 'like', $like);
        });
    }

    /**
     * Shape consumed by the pick-list and the mass inventory table.
     *
     * @return array<string, mixed>
     */
    public function toPickOption(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'category' => $this->category,
            'unit_price' => (float) $this->unit_price,
            'stock_quantity' => (int) $this->stock_quantity,
            'is_fragile' => (bool) $this->is_fragile,
            'is_blocked' => $this->is_blocked,
            'photo_url' => $this->photo_url,
            'initials' => $this->initials,
        ];
    }
}
