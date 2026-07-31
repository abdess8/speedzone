<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim projection of an order for the paginated list screen.
 *
 * OrderResource describes a single order in full (partner, invoice, transfers,
 * status timeline, seller summary, ...). Rendering 25-100 of those per page
 * shipped roughly four times the data the table actually displays, so the list
 * gets its own resource limited to the rendered columns.
 *
 * @mixin Order
 */
class OrderListResource extends JsonResource
{
    /**
     * Columns this resource needs. Used to keep the underlying select narrow so
     * heavy text columns (notes, sync errors) never leave MySQL.
     *
     * @var array<int, string>
     */
    public const COLUMNS = [
        'id',
        'tracking_number',
        'customer_first_name',
        'customer_last_name',
        'customer_phone',
        'customer_address',
        'city_id',
        'sector_id',
        'payment_method',
        'order_value',
        'order_amount',
        'delivery_price',
        'total_amount',
        'status',
        'is_fragile',
        'can_be_opened',
        'return_id',
        'created_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof OrderStatus
            ? $this->status
            : OrderStatus::from($this->status);

        $payment = $this->payment_method instanceof PaymentMethod
            ? $this->payment_method
            : PaymentMethod::resolve((string) $this->payment_method);

        $orderAmount = $this->order_amount !== null ? (float) $this->order_amount : null;

        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,

            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),

            'customer' => [
                'full_name' => $this->customer_full_name,
                'phone' => $this->customer_phone,
                // Rendered by the driver card, not by the desktop table.
                'address' => $this->customer_address,
            ],

            'city' => $this->whenLoaded('city', fn () => $this->city ? [
                'id' => $this->city->id,
                'name' => $this->city->name,
            ] : null),

            'sector' => $this->whenLoaded('sector', fn () => $this->sector ? [
                'id' => $this->sector->id,
                'name' => $this->sector->name,
            ] : null),

            'payment_method' => $payment->value,
            'payment_method_label' => $payment->label(),
            'payment_method_emoji' => $payment->emoji(),
            'payment_method_color' => $payment->color(),

            'amount_to_collect' => $payment->amountToCollect($orderAmount),
            // An empty "to collect" cell is ambiguous on a driver's card: it
            // reads as missing data rather than "nothing to collect". This flag
            // lets the card state it outright.
            'is_already_paid' => ! $payment->requiresCashCollection(),
            'order_value' => $this->order_value !== null ? (float) $this->order_value : null,
            'delivery_price' => (float) $this->delivery_price,
            'total_amount' => (float) $this->total_amount,

            'is_fragile' => (bool) $this->is_fragile,
            'can_be_opened' => (bool) $this->can_be_opened,

            // Gates the driver card's "open a return" action. Deliberately
            // coarse: a cancelled return makes the order eligible again, but the
            // list would need a join to know that, and ReturnService rejects a
            // duplicate anyway.
            'has_return' => $this->return_id !== null,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
