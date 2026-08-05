<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product line of an inbound shipment.
 */
class StockReceptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_reception_id',
        'product_id',
        'quantity_sent',
        'quantity_collected',
        'quantity_received',
        'quantity_rejected',
        'note',
    ];

    protected $casts = [
        'quantity_sent' => 'integer',
        'quantity_collected' => 'integer',
        'quantity_received' => 'integer',
        'quantity_rejected' => 'integer',
    ];

    public function reception(): BelongsTo
    {
        return $this->belongsTo(StockReception::class, 'stock_reception_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The figure the depot counts against.
     *
     * Once a collector has signed for the line, his count is the reference: the
     * vendor's declaration was superseded at the shop door, and holding the depot
     * to it would report a shortage twice.
     */
    public function baselineQuantity(): int
    {
        return $this->quantity_collected ?? $this->quantity_sent;
    }

    /**
     * Units the collector did not take away, null while nobody has been to the shop.
     */
    public function collectionGap(): ?int
    {
        if ($this->quantity_collected === null) {
            return null;
        }

        return $this->quantity_collected - $this->quantity_sent;
    }

    /**
     * Difference between what was handed to us and what the hub counted.
     *
     * Null while the line has not been counted yet — an uncounted line is not
     * the same thing as a line counted at zero.
     */
    public function discrepancy(): ?int
    {
        if ($this->quantity_received === null) {
            return null;
        }

        return $this->quantity_received + (int) $this->quantity_rejected - $this->baselineQuantity();
    }
}
