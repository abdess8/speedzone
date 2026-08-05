<?php

namespace App\Models;

use App\Enums\StockReceptionStatus;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inbound shipment: stock travelling from a vendor to one of our depots.
 *
 * Three counts of the same goods live on this document — what the vendor declared,
 * what the collector took away from the shop, and what the depot signed for. Only
 * the last one reaches the catalog; the first two exist so that a missing unit can
 * be placed either at the shop or on the road.
 */
class StockReception extends Model
{
    use BelongsToStore;
    use HasFactory;

    protected $fillable = [
        'reference',
        'seller_id',
        'store_id',
        'status',
        'destination_city_id',
        'sent_at',
        'sent_by',
        'sending_notes',
        'collected_by',
        'collected_at',
        'collection_notes',
        'dispatched_at',
        'received_at',
        'received_by',
        'reception_notes',
        'validated_at',
    ];

    protected $casts = [
        'status' => StockReceptionStatus::class,
        'sent_at' => 'date',
        'collected_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at' => 'date',
        'validated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $reception): void {
            if (empty($reception->status)) {
                $reception->status = StockReceptionStatus::DRAFT->value;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** The vendor or team member who prepared and shipped the parcel. */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** The collector who went to the shop, counted the goods and drove them away. */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /** The hub agent who counted and scanned it in. */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** The depot city the vendor addressed the parcel to. */
    public function destinationCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'destination_city_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceptionItem::class)->orderBy('id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StockReceptionStatusHistory::class)->orderBy('id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function statusEnum(): StockReceptionStatus
    {
        return $this->status instanceof StockReceptionStatus
            ? $this->status
            : StockReceptionStatus::from((string) $this->status);
    }

    public function isEditableByVendor(): bool
    {
        return $this->statusEnum()->isEditableByVendor();
    }

    /**
     * Journal a step of the shipment's journey.
     *
     * Called for every status change including the very first, so the timeline
     * opens on the moment the vendor started the slip rather than on whatever
     * happened to it next.
     */
    public function recordStatus(
        StockReceptionStatus|string|null $from,
        StockReceptionStatus|string $to,
        ?User $actor = null,
        ?string $comment = null,
    ): StockReceptionStatusHistory {
        return $this->statusHistories()->create([
            'old_status' => $from instanceof StockReceptionStatus ? $from->value : $from,
            'new_status' => $to instanceof StockReceptionStatus ? $to->value : $to,
            'changed_by' => $actor?->id,
            'comment' => $comment,
        ]);
    }

    /**
     * Where a collector has to drive to get the parcel.
     *
     * The shop's own city, not the depot's: collection happens at the vendor's
     * counter. Falls back to the account owner's city for a shop registered
     * without one.
     */
    public function pickupCityId(): ?int
    {
        $cityId = $this->store?->city_id ?? $this->seller?->city_id;

        return $cityId === null ? null : (int) $cityId;
    }

    public function totalSent(): int
    {
        return (int) $this->items->sum('quantity_sent');
    }

    /** Units the collector signed for at the shop, null while nobody has been. */
    public function totalCollected(): ?int
    {
        if ($this->items->whereNotNull('quantity_collected')->isEmpty()) {
            return null;
        }

        return (int) $this->items->sum('quantity_collected');
    }

    public function totalReceived(): int
    {
        return (int) $this->items->sum('quantity_received');
    }

    public function totalRejected(): int
    {
        return (int) $this->items->sum('quantity_rejected');
    }

    /**
     * Units declared but never accounted for, either as received or rejected.
     *
     * Non-zero means the shipment does not add up, which is exactly what the hub
     * notes are for.
     */
    public function unaccountedUnits(): int
    {
        return $this->totalSent() - $this->totalReceived() - $this->totalRejected();
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

    public function scopeWithStatus(Builder $query, StockReceptionStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof StockReceptionStatus ? $status->value : $status);
    }

    /** Shipments a collector still has to go and get. */
    public function scopeAwaitingPickup(Builder $query): Builder
    {
        return $query->where('status', StockReceptionStatus::AWAITING_PICKUP->value);
    }

    /** Shipments the depot is expected to count: the ones already on the road. */
    public function scopeAwaitingReception(Builder $query): Builder
    {
        return $query->where('status', StockReceptionStatus::IN_TRANSIT->value);
    }

    public function scopeCollectedBy(Builder $query, int $userId): Builder
    {
        return $query->where('collected_by', $userId);
    }

    public function scopeForDestinationCity(Builder $query, int $cityId): Builder
    {
        return $query->where('destination_city_id', $cityId);
    }

    /**
     * Shipments to be picked up in one of these cities.
     *
     * Reads the shop's city, with the owner's own city as the fallback the
     * document helper uses — so the queue a collector sees matches the address he
     * would be driving to.
     */
    public function scopeForPickupCities(Builder $query, array $cityIds): Builder
    {
        if ($cityIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scoped) use ($cityIds): void {
            $scoped->whereHas('store', fn (Builder $store) => $store->whereIn('city_id', $cityIds))
                ->orWhere(function (Builder $orphan) use ($cityIds): void {
                    $orphan->whereDoesntHave('store', fn (Builder $store) => $store->whereNotNull('city_id'))
                        ->whereHas('seller', fn (Builder $seller) => $seller->whereIn('city_id', $cityIds));
                });
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
