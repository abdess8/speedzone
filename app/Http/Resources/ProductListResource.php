<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a product for the catalog and inventory screens.
 *
 * @mixin Product
 */
class ProductListResource extends JsonResource
{
    /**
     * Columns this resource needs, so the description — the one heavy text column
     * on the table — never leaves MySQL for a list of a hundred rows.
     *
     * @var array<int, string>
     */
    public const COLUMNS = [
        'id',
        'store_id',
        'seller_id',
        'name',
        'sku',
        'barcode',
        'category',
        'photo_path',
        'unit_price',
        'cost_price',
        'stock_quantity',
        'is_fragile',
        'is_active',
        'blocked_at',
        'blocked_reason',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $threshold = (int) config('stock.low_stock_threshold', 5);
        $stock = (int) $this->stock_quantity;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'category' => $this->category,

            'photo_url' => $this->photo_url,
            'initials' => $this->initials,

            'unit_price' => (float) $this->unit_price,
            'cost_price' => $this->cost_price !== null ? (float) $this->cost_price : null,
            'margin' => $this->margin(),

            'stock_quantity' => $stock,
            'is_out_of_stock' => $stock <= 0,
            'is_low_stock' => $stock > 0 && $stock <= $threshold,

            'is_fragile' => (bool) $this->is_fragile,
            'is_active' => (bool) $this->is_active,
            'is_blocked' => $this->is_blocked,
            'blocked_reason' => $this->blocked_reason,

            'seller' => $this->whenLoaded('seller', fn () => $this->seller ? [
                'id' => $this->seller->id,
                'name' => $this->seller->full_name,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
