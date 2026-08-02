<?php

namespace App\Models;

use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnStatus;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    use BelongsToStore;
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'reference',
        'order_id',
        'store_id',
        'created_by',
        'initiated_by_role',
        'reason',
        'status',
        'current_location_city_id',
        'return_address',
        'return_notes',
        'updated_customer_name',
        'updated_customer_phone',
        'updated_address',
        'updated_city_id',
    ];

    protected $casts = [
        'status' => ReturnStatus::class,
        'initiated_by_role' => ReturnInitiatedByRole::class,
    ];

    protected static function booted(): void
    {
        // A return is almost always opened by a driver or by back-office staff,
        // neither of whom stands on a store, so BelongsToStore has nothing to
        // copy. The store is inherited from the order being reversed instead.
        static::creating(function (self $return): void {
            if ($return->store_id === null && $return->order_id !== null) {
                $return->store_id = Order::acrossStores()
                    ->whereKey($return->order_id)
                    ->value('store_id');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creator(): BelongsTo
    {
        return $this->createdBy();
    }

    public function currentLocationCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'current_location_city_id');
    }

    public function updatedCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'updated_city_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReturnStatusHistory::class, 'return_id')->orderBy('created_at')->orderBy('id');
    }

    public function recordStatus(
        ReturnStatus|string $newStatus,
        ?User $actor = null,
        ?string $oldStatus = null,
        ?string $comment = null
    ): ReturnStatusHistory {
        $new = $newStatus instanceof ReturnStatus ? $newStatus->value : $newStatus;

        return $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $new,
            'changed_by' => $actor?->id,
            'comment' => $comment,
        ]);
    }

    public function scanUrl(): string
    {
        $base = rtrim((string) config('returns.tracking_base_url', config('app.url')), '/');

        return $base.'/returns/'.$this->reference;
    }

    public function canEditCustomerData(): bool
    {
        $status = $this->status instanceof ReturnStatus ? $this->status : ReturnStatus::from($this->status);

        return $status->allowsCustomerDataEdit();
    }

    public function isTerminal(): bool
    {
        $status = $this->status instanceof ReturnStatus ? $this->status : ReturnStatus::from($this->status);

        return $status->isTerminal();
    }

    /**
     * Effective customer name for return processing (override or original order).
     */
    public function effectiveCustomerName(): string
    {
        if ($this->updated_customer_name) {
            return $this->updated_customer_name;
        }

        return $this->order?->customer_full_name ?? '';
    }

    /**
     * Effective customer phone for return processing.
     */
    public function effectiveCustomerPhone(): ?string
    {
        return $this->updated_customer_phone ?? $this->order?->customer_phone;
    }

    /**
     * Effective return address for processing.
     */
    public function effectiveAddress(): ?string
    {
        return $this->updated_address ?? $this->return_address ?? $this->order?->customer_address;
    }

    /**
     * Effective city for return processing.
     */
    public function effectiveCityId(): ?int
    {
        return $this->updated_city_id ?? $this->current_location_city_id ?? $this->order?->city_id;
    }

    public function scopeOwnedBySeller(Builder $query, int $sellerId): Builder
    {
        return $query->whereHas('order', fn (Builder $q) => $q->where('seller_id', $sellerId));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ReturnStatus::DELIVERED_TO_SELLER->value,
            ReturnStatus::CANCELLED->value,
        ]);
    }
}
