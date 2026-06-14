<?php

namespace App\Models;

use App\Enums\PickupRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'created_by',
        'assigned_to',
        'status',
        'pickup_address',
        'number_of_packages',
        'total_orders_amount',
        'notes',
    ];

    protected $casts = [
        'status' => PickupRequestStatus::class,
        'number_of_packages' => 'integer',
        'total_orders_amount' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PickupStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function recordStatus(
        PickupRequestStatus|string $newStatus,
        ?User $actor = null,
        ?string $oldStatus = null,
        ?string $comment = null
    ): PickupStatusHistory {
        $new = $newStatus instanceof PickupRequestStatus ? $newStatus->value : $newStatus;

        return $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $new,
            'changed_by' => $actor?->id,
            'comment' => $comment,
        ]);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }
}
