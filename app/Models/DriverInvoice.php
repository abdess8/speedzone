<?php

namespace App\Models;

use App\Enums\DriverInvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'driver_id',
        'period_start',
        'period_end',
        'deliveries_count',
        'total_amount',
        'status',
        'generated_at',
        'paid_at',
        'paid_by',
        'payment_receipt_attachment',
        'pdf_file',
        'created_by',
    ];

    protected $casts = [
        'status' => DriverInvoiceStatus::class,
        'period_start' => 'date',
        'period_end' => 'date',
        'deliveries_count' => 'integer',
        'total_amount' => 'decimal:2',
        'generated_at' => 'datetime',
        'paid_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function invoiceTransactions(): HasMany
    {
        return $this->hasMany(DriverInvoiceTransaction::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(DriverTransaction::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DriverFinanceLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the invoice snapshot is frozen (anything other than DRAFT).
     */
    public function isLocked(): bool
    {
        $status = $this->status instanceof DriverInvoiceStatus ? $this->status : DriverInvoiceStatus::from($this->status);

        return $status->isLocked();
    }

    /**
     * Record an entry in the driver finance audit trail for this invoice.
     */
    public function log(string $action, ?User $actor = null, mixed $oldValue = null, mixed $newValue = null): DriverFinanceLog
    {
        return $this->logs()->create([
            'driver_id' => $this->driver_id,
            'action' => $action,
            'user_id' => $actor?->id,
            'old_value' => $this->stringifyLogValue($oldValue),
            'new_value' => $this->stringifyLogValue($newValue),
        ]);
    }

    private function stringifyLogValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
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

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
