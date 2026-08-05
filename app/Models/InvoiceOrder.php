<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'order_id',
        'order_amount',
        'delivery_fee',
        'return_fee',
        'final_amount',
        'order_status_at_invoice',
        'order_completed_at',
    ];

    protected $casts = [
        'order_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'return_fee' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'order_completed_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
