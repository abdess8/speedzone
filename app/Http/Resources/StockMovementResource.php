<?php

namespace App\Http\Resources;

use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the stock ledger, as read by the audit screen.
 *
 * @mixin StockAdjustment
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'product' => [
                'id' => $this->product_id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
            ],
            'store' => $this->store?->name,

            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'source_color' => $this->source->color(),
            'source_icon' => $this->source->icon(),

            'reason' => $this->reason?->value,
            'reason_label' => $this->reason?->label(),
            'reason_color' => $this->reason?->color(),
            'note' => $this->note,

            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'delta' => $this->delta,

            'author' => $this->author?->full_name,
            // The document the movement came from, when it came from one: a
            // credit nobody can trace back to a slip is not auditable.
            'reception' => $this->reception?->reference,
            'order' => $this->order?->tracking_number,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
