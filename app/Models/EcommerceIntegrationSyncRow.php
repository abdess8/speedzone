<?php

namespace App\Models;

use App\Enums\EcommerceSyncRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceIntegrationSyncRow extends Model
{
    protected $fillable = [
        'ecommerce_integration_id',
        'ecommerce_integration_sync_id',
        'external_order_id',
        'ref',
        'status',
        'values',
        'raw',
        'errors',
    ];

    protected $casts = [
        'status' => EcommerceSyncRowStatus::class,
        'values' => 'array',
        'raw' => 'array',
        'errors' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(EcommerceIntegration::class, 'ecommerce_integration_id');
    }

    public function sync(): BelongsTo
    {
        return $this->belongsTo(EcommerceIntegrationSync::class, 'ecommerce_integration_sync_id');
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, [
            EcommerceSyncRowStatus::Pending,
            EcommerceSyncRowStatus::Failed,
        ], true);
    }
}
