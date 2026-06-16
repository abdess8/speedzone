<?php

namespace App\Models;

use App\Enums\DriverTransactionStatus;
use App\Enums\DriverTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'order_id',
        'sector_id',
        'driver_invoice_id',
        'amount',
        'driver_price_snapshot',
        'transaction_type',
        'status',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'driver_price_snapshot' => 'decimal:2',
        'transaction_type' => DriverTransactionType::class,
        'status' => DriverTransactionStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function driverInvoice(): BelongsTo
    {
        return $this->belongsTo(DriverInvoice::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForDriver(Builder $query, int $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * Transactions that are confirmed and not yet attached to any invoice.
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query
            ->whereNull('driver_invoice_id')
            ->where('status', DriverTransactionStatus::CONFIRMED->value);
    }

    /**
     * Whether this transaction is frozen because it belongs to a paid invoice.
     */
    public function isLocked(): bool
    {
        $status = $this->status instanceof DriverTransactionStatus
            ? $this->status
            : DriverTransactionStatus::from($this->status);

        return $this->driver_invoice_id !== null || $status->isLocked();
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
