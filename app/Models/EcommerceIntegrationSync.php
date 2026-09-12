<?php

namespace App\Models;

use App\Enums\EcommerceSyncStatus;
use App\Enums\EcommerceSyncTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceIntegrationSync extends Model
{
    protected $fillable = [
        'ecommerce_integration_id',
        'status',
        'trigger',
        'fetched_count',
        'created_count',
        'updated_count',
        'skipped_count',
        'error_count',
        'started_at',
        'finished_at',
        'error_message',
        'skipped_reasons',
        'metadata',
    ];

    protected $casts = [
        'status' => EcommerceSyncStatus::class,
        'trigger' => EcommerceSyncTrigger::class,
        'fetched_count' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'skipped_count' => 'integer',
        'error_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'skipped_reasons' => 'array',
        'metadata' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(EcommerceIntegration::class, 'ecommerce_integration_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'ecommerce_sync_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(EcommerceIntegrationSyncRow::class, 'ecommerce_integration_sync_id');
    }

    public function durationSeconds(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at);
    }

    public function isRunning(): bool
    {
        return $this->status === EcommerceSyncStatus::Running;
    }
}
