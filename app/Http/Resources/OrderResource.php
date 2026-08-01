<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PickupRequestStatus;
use App\Enums\TransferStatus;
use App\Enums\DriverInvoiceStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof OrderStatus ? $this->status : OrderStatus::from($this->status);
        $payment = $this->payment_method instanceof PaymentMethod
            ? $this->payment_method
            : PaymentMethod::resolve((string) $this->payment_method);

        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'order_number' => $this->tracking_number,
            'tracking_url' => $this->trackingUrl(),

            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),

            'customer' => [
                'first_name' => $this->customer_first_name,
                'last_name' => $this->customer_last_name,
                'full_name' => $this->customer_full_name,
                'phone' => $this->customer_phone,
                'address' => $this->customer_address,
            ],

            'city' => $this->whenLoaded('city', fn () => $this->city ? [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'region' => $this->city->region,
            ] : null),
            'city_id' => $this->city_id,

            'pickup_city' => $this->when(
                $this->relationLoaded('seller') && $this->seller?->relationLoaded('city'),
                fn () => $this->seller?->city ? [
                    'id' => $this->seller->city->id,
                    'name' => $this->seller->city->name,
                ] : null
            ),

            'sector' => $this->whenLoaded('sector', fn () => $this->sector ? [
                'id' => $this->sector->id,
                'name' => $this->sector->name,
                'delivery_price' => (float) $this->sector->delivery_price,
            ] : null),
            'sector_id' => $this->sector_id,

            'seller' => $this->whenLoaded('seller', fn () => $this->seller
                ? UserSummaryResource::make($this->seller)->resolve($request)
                : null),
            'seller_id' => $this->seller_id,

            // Store of origin: also what the shipping label is branded with.
            'store' => $this->whenLoaded('store', fn () => $this->store ? [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'category' => $this->store->category,
                'logo_url' => $this->store->logo_url,
            ] : null),
            'store_id' => $this->store_id,

            'partner_id' => $this->partner_id,
            'external_tracking_code' => $this->external_tracking_code,
            'partner_sync_error' => $this->partner_sync_error,
            'is_partner_delivery' => $this->isPartnerDelivery(),
            'partner' => $this->whenLoaded('partner', function () {
                if (! $this->partner) {
                    return null;
                }

                $connection = 'connected';
                if (! $this->partner->is_active) {
                    $connection = 'inactive';
                } elseif (! filled($this->partner->access_token)) {
                    $connection = 'no_token';
                } elseif ($this->partner->token_expires_at?->isPast()) {
                    $connection = 'expired';
                }

                return [
                    'id' => $this->partner->id,
                    'name' => $this->partner->name,
                    'logo_url' => $this->partner->logo_url,
                    'is_active' => (bool) $this->partner->is_active,
                    'has_access_token' => filled($this->partner->access_token),
                    'token_expires_at' => $this->partner->token_expires_at?->toIso8601String(),
                    'sync_status' => (bool) $this->partner->sync_status,
                    'connection_status' => $connection,
                    'connection_label' => __("orders.show.partner_connection.{$connection}"),
                    'connection_color' => match ($connection) {
                        'connected' => 'success',
                        'expired' => 'danger',
                        'no_token' => 'warning',
                        default => 'secondary',
                    },
                ];
            }),

            'driver' => $this->whenLoaded('driver', fn () => $this->driver
                ? UserSummaryResource::make($this->driver)->resolve($request)
                : null),
            'driver_id' => $this->driver_id,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),

            'pickup_request' => $this->whenLoaded('pickupRequest', function () use ($request) {
                if (! $this->pickupRequest) {
                    return null;
                }

                $status = $this->pickupRequest->status instanceof PickupRequestStatus
                    ? $this->pickupRequest->status
                    : PickupRequestStatus::from($this->pickupRequest->status);

                return [
                    'id' => $this->pickupRequest->id,
                    'reference' => $this->pickupRequest->reference,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_color' => $status->color(),
                    'pickup_address' => $this->pickupRequest->pickup_address,
                    'created_at' => $this->pickupRequest->created_at?->toIso8601String(),
                    'created_by' => $this->pickupRequest->relationLoaded('createdBy') && $this->pickupRequest->createdBy
                        ? UserSummaryResource::make($this->pickupRequest->createdBy)->resolve($request)
                        : null,
                    'assigned_driver' => $this->pickupRequest->relationLoaded('assignedDriver') && $this->pickupRequest->assignedDriver
                        ? UserSummaryResource::make($this->pickupRequest->assignedDriver)->resolve($request)
                        : null,
                ];
            }),

            'invoice' => $this->whenLoaded('invoice', function () {
                if (! $this->invoice) {
                    return null;
                }

                $status = $this->invoice->status instanceof \App\Enums\InvoiceStatus
                    ? $this->invoice->status
                    : \App\Enums\InvoiceStatus::from($this->invoice->status);

                return [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'reference' => $this->invoice->invoice_number,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_color' => $status->color(),
                ];
            }),
            'invoice_id' => $this->invoice_id,

            'driver_invoice' => $this->whenLoaded('driverTransactions', function () {
                $transaction = $this->driverTransactions
                    ->first(fn ($tx) => $tx->driver_invoice_id !== null && $tx->relationLoaded('driverInvoice') && $tx->driverInvoice);

                if (! $transaction?->driverInvoice) {
                    return null;
                }

                $invoice = $transaction->driverInvoice;
                $status = $invoice->status instanceof DriverInvoiceStatus
                    ? $invoice->status
                    : DriverInvoiceStatus::from($invoice->status);

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'reference' => $invoice->invoice_number,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_color' => $status->color(),
                ];
            }),

            'is_returned' => (bool) $this->is_returned,

            'active_return' => $this->whenLoaded('orderReturn', function () use ($request) {
                if (! $this->orderReturn) {
                    return null;
                }

                $status = $this->orderReturn->status instanceof \App\Enums\ReturnStatus
                    ? $this->orderReturn->status
                    : \App\Enums\ReturnStatus::from($this->orderReturn->status);

                if ($status === \App\Enums\ReturnStatus::CANCELLED) {
                    return null;
                }

                return OrderReturnResource::make($this->orderReturn)->resolve($request);
            }),

            'active_transfer' => $this->whenLoaded('transfers', function () {
                $transfer = $this->transfers
                    ->first(fn ($t) => ($t->status instanceof TransferStatus ? $t->status : TransferStatus::from($t->status)) !== TransferStatus::CANCELLED);

                if (! $transfer) {
                    return null;
                }

                $status = $transfer->status instanceof TransferStatus
                    ? $transfer->status
                    : TransferStatus::from($transfer->status);

                return [
                    'id' => $transfer->id,
                    'reference' => $transfer->reference,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_color' => $status->color(),
                ];
            }),

            'payment_method' => $payment->value,
            'payment_method_label' => $payment->label(),
            'payment_method_display' => $payment->displayLabel(),
            'payment_method_icon' => $payment->icon(),
            'payment_method_emoji' => $payment->emoji(),
            'payment_method_color' => $payment->color(),
            'cash_collection_required' => $payment->requiresCashCollection(),

            'order_amount' => $this->order_amount !== null ? (float) $this->order_amount : null,
            'order_value' => $this->order_value !== null ? (float) $this->order_value : null,
            'amount_to_collect' => $payment->amountToCollect(
                $this->order_amount !== null ? (float) $this->order_amount : null
            ),
            'delivery_price' => (float) $this->delivery_price,
            'total_amount' => (float) $this->total_amount,

            'notes' => $this->notes,
            'is_fragile' => (bool) $this->is_fragile,
            'can_be_opened' => (bool) $this->can_be_opened,
            'option_exchange' => (bool) $this->option_exchange,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Resolve to a plain array (no "data" wrapper) so the frontend can
            // iterate the timeline entries directly.
            'status_history' => $this->whenLoaded(
                'statusHistories',
                fn () => OrderStatusHistoryResource::collection($this->statusHistories)->resolve($request)
            ),

            'change_history' => $this->whenLoaded(
                'changeHistories',
                fn () => OrderChangeHistoryResource::collection($this->changeHistories)->resolve($request)
            ),
        ];
    }
}
