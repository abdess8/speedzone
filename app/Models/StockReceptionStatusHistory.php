<?php

namespace App\Models;

use App\Enums\StockReceptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of an inbound shipment's journey, with the hands that moved it.
 *
 * Append-only: `$timestamps` is off because the row carries a `created_at` and
 * nothing else, and an audit line that can be updated answers nothing.
 */
class StockReceptionStatusHistory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'stock_reception_id',
        'old_status',
        'new_status',
        'changed_by',
        'comment',
    ];

    protected $casts = [
        'old_status' => StockReceptionStatus::class,
        'new_status' => StockReceptionStatus::class,
        'created_at' => 'datetime',
    ];

    public function reception(): BelongsTo
    {
        return $this->belongsTo(StockReception::class, 'stock_reception_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
