<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A catalog line on an order.
 *
 * Name, reference and unit price are copied rather than read through the
 * relation: the line has to keep saying what was sold at the price it was sold
 * for, whatever happens to the product sheet afterwards.
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'sku',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Line total for a quantity at a price, rounded the way money is stored.
     */
    public static function computeLineTotal(float $unitPrice, int $quantity): float
    {
        return round($unitPrice * $quantity, 2);
    }
}
