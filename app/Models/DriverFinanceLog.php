<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverFinanceLog extends Model
{
    use HasFactory;

    public const ACTION_INVOICE_CREATED = 'invoice_created';

    public const ACTION_INVOICE_PAID = 'invoice_paid';

    public const ACTION_INVOICE_CANCELLED = 'invoice_cancelled';

    public const ACTION_INVOICE_DELETED = 'invoice_deleted';

    public const ACTION_TRANSACTION_CREATED = 'transaction_created';

    public const ACTION_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'driver_id',
        'driver_invoice_id',
        'action',
        'user_id',
        'old_value',
        'new_value',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function driverInvoice(): BelongsTo
    {
        return $this->belongsTo(DriverInvoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
