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
        'amount_snapshot',
    ];

    protected $casts = [
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
