<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A vendor's shop: the isolation boundary every business row hangs from.
 *
 * Owned by a single seller (`owner_id`), readable by whoever appears in the
 * `store_user` pivot — the owner included.
 */
class Store extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'category',
        'website',
        'logo_path',
        'contact_name',
        'contact_phone',
        'contact_email',
        'city_id',
        'stock_hub_city_id',
        'address',
        'pickup_address_1',
        'pickup_address_2',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * The depot holding this shop's stock.
     *
     * Unrelated to {@see self::city()}, which is where the vendor himself sits:
     * a Marrakech shop may warehouse in Casablanca. One depot per shop, so a
     * stock order always has exactly one city to ship out of.
     */
    public function stockHubCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'stock_hub_city_id');
    }

    /**
     * Everyone allowed to switch into this store (owner + team members).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function pickupRequests(): HasMany
    {
        return $this->hasMany(PickupRequest::class);
    }

    /**
     * Logo shown in the switcher and printed on this store's shipping labels.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? User::publicStorageUrl($this->logo_path) : null;
    }

    public function scopeOwnedBy(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
