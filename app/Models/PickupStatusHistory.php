<?php

namespace App\Models;

use App\Enums\PickupRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'pickup_request_id',
        'old_status',
        'new_status',
        'changed_by',
        'comment',
    ];

    protected $casts = [
        'old_status' => PickupRequestStatus::class,
        'new_status' => PickupRequestStatus::class,
    ];

    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
