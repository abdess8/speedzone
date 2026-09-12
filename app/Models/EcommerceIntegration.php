<?php

namespace App\Models;

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommercePlatform;
use App\Enums\EcommerceSyncRowStatus;
use App\Enums\EcommerceSyncStatus;
use App\Services\Ecommerce\YouCan\YouCanFieldCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class EcommerceIntegration extends Model
{
    /**
     * Minutes a seller can pick as the auto-sync cadence. The scheduler
     * itself ticks every five minutes; these are just the gaps between runs.
     *
     * @var array<int, int>
     */
    public const SYNC_INTERVALS = [5, 10, 15, 30, 60, 120, 1440];

    protected $fillable = [
        'seller_id',
        'store_id',
        'platform',
        'status',
        'shop_slug',
        'shop_name',
        'external_store_id',
        'email',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'last_error',
        'connected_at',
        'connected_by',
        'auto_sync_enabled',
        'sync_interval_minutes',
        'import_status',
        'field_mapping',
        'source_fields',
        'last_synced_at',
        'next_sync_at',
    ];

    protected $casts = [
        'platform' => EcommercePlatform::class,
        'status' => EcommerceIntegrationStatus::class,
        'client_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'auto_sync_enabled' => 'boolean',
        'sync_interval_minutes' => 'integer',
        'field_mapping' => 'array',
        'source_fields' => 'array',
        'last_synced_at' => 'datetime',
        'next_sync_at' => 'datetime',
    ];

    /**
     * Credentials must never ride an Inertia or JSON payload.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function syncs(): HasMany
    {
        return $this->hasMany(EcommerceIntegrationSync::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return array<string, string|null>
     */
    public function resolvedFieldMapping(): array
    {
        $defaults = YouCanFieldCatalog::autoMap($this->source_fields ?: YouCanFieldCatalog::builtinSources());

        return YouCanFieldCatalog::mergeMapping($this->field_mapping, $defaults);
    }

    public function latestReviewableSync(): ?EcommerceIntegrationSync
    {
        return $this->syncs()
            ->whereHas('rows', function ($query) {
                $query->whereIn('status', [
                    EcommerceSyncRowStatus::Pending->value,
                    EcommerceSyncRowStatus::Failed->value,
                ]);
            })
            ->latest('id')
            ->first();
    }

    public function isConnected(): bool
    {
        return $this->status === EcommerceIntegrationStatus::Connected
            && filled($this->access_token);
    }

    public function hasPassword(): bool
    {
        return filled($this->client_secret);
    }

    public function hasClientSecret(): bool
    {
        return $this->hasPassword();
    }

    public function hasAccessToken(): bool
    {
        return filled($this->access_token);
    }

    public function scopeForPlatform(Builder $query, EcommercePlatform $platform): Builder
    {
        return $query->where('platform', $platform->value);
    }

    public function scopeForSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    public function hasRunningSync(): bool
    {
        return $this->syncs()
            ->where('status', EcommerceSyncStatus::Running->value)
            ->exists();
    }

    public function scheduleNextSync(?\DateTimeInterface $from = null): void
    {
        if (! $this->auto_sync_enabled) {
            $this->next_sync_at = null;

            return;
        }

        $interval = in_array($this->sync_interval_minutes, self::SYNC_INTERVALS, true)
            ? $this->sync_interval_minutes
            : 15;

        $this->next_sync_at = Carbon::parse($from ?? now())
            ->addMinutes($interval);
    }
}
