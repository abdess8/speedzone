<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverInvoiceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_invoice_id',
        'driver_transaction_id',
        'collected_snapshot',
        'commission_snapshot',
        'amount_snapshot',
    ];

    protected $casts = [
        'collected_snapshot' => 'decimal:2',
        'commission_snapshot' => 'decimal:2',
        'amount_snapshot' => 'decimal:2',
    ];

    public function driverInvoice(): BelongsTo
    {
        return $this->belongsTo(DriverInvoice::class);
    }

    public function driverTransaction(): BelongsTo
    {
        return $this->belongsTo(DriverTransaction::class);
    }
}
