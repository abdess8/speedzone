<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToStore;
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'seller_id',
        'store_id',
        'period_start',
        'period_end',
        'total_orders_count',
        'delivered_amount',
        'returned_amount',
        'delivery_fees_total',
        'return_fees_total',
        'gross_amount',
        'net_amount',
        'status',
        'generated_at',
        'paid_at',
        'paid_by',
        'payment_receipt_attachment',
        'pdf_file',
        'created_by',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'period_start' => 'date',
        'period_end' => 'date',
        'total_orders_count' => 'integer',
        'delivered_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2',
        'delivery_fees_total' => 'decimal:2',
        'return_fees_total' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'generated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function invoiceOrders(): HasMany
    {
        return $this->hasMany(InvoiceOrder::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InvoiceLog::class)->orderByDesc('created_at')->orderByDesc('id');
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
        $status = $this->status instanceof InvoiceStatus ? $this->status : InvoiceStatus::from($this->status);

        return $status->isLocked();
    }

    /**
     * Record an entry in the invoice audit trail.
     */
    public function log(string $action, ?User $actor = null, mixed $oldValue = null, mixed $newValue = null): InvoiceLog
    {
        return $this->logs()->create([
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

    public function scopeForSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
