<?php

namespace App\Models;

use App\Enums\TransferContentType;
use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'from_city_id',
        'to_city_id',
        'created_by',
        'assigned_to',
        'status',
        'content_type',
        'number_of_packages',
        'number_of_returns',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'status' => TransferStatus::class,
        'content_type' => TransferContentType::class,
        'number_of_packages' => 'integer',
        'number_of_returns' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    public function fromCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'from_city_id');
    }

    public function toCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'to_city_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creator(): BelongsTo
    {
        return $this->createdBy();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignee(): BelongsTo
    {
        return $this->assignedTo();
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'transfer_orders')
            ->withPivot('created_at');
    }

    public function transferOrders(): HasMany
    {
        return $this->hasMany(TransferOrder::class);
    }

    public function returns(): BelongsToMany
    {
        return $this->belongsToMany(OrderReturn::class, 'transfer_returns', 'transfer_id', 'return_id')
            ->withPivot('created_at');
    }

    public function transferReturns(): HasMany
    {
        return $this->hasMany(TransferReturn::class);
    }

    public function contentType(): TransferContentType
    {
        return $this->content_type instanceof TransferContentType
            ? $this->content_type
            : TransferContentType::from($this->content_type ?? TransferContentType::ORDERS->value);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TransferStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function recordStatus(
        TransferStatus|string $newStatus,
        ?User $actor = null,
        ?string $oldStatus = null,
        ?string $comment = null
    ): TransferStatusHistory {
        $new = $newStatus instanceof TransferStatus ? $newStatus->value : $newStatus;

        return $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $new,
            'changed_by' => $actor?->id,
            'comment' => $comment,
        ]);
    }

    /**
     * Public URL encoded inside the transfer QR code.
     */
    public function scanUrl(): string
    {
        $base = rtrim((string) config('transfer.tracking_base_url', config('app.url')), '/');

        return $base.'/transfers/'.$this->reference;
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }
}
