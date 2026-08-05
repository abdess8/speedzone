<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'speedzone_status',
        'partner_status',
    ];

    protected $casts = [
        // Our side of the mapping is always a known OrderStatus.
        'speedzone_status' => OrderStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('partner_id', $partnerId);
    }
}
