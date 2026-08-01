<?php

namespace App\Http\Resources;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof InvoiceStatus ? $this->status : InvoiceStatus::from($this->status);

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,

            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'status_icon' => $status->icon(),
            'is_locked' => $status->isLocked(),

            'seller' => $this->whenLoaded('seller', fn () => $this->seller
                ? UserSummaryResource::make($this->seller)->resolve($request)
                : null),
            'seller_id' => $this->seller_id,

            'seller_billing' => $this->when(
                $this->relationLoaded('seller') && $this->seller,
                fn () => [
                    'bank_name' => $this->seller->bank_name,
                    'rib' => $this->seller->rib,
                    'payment_method' => $this->seller->payment_method instanceof \BackedEnum
                        ? $this->seller->payment_method->value
                        : $this->seller->payment_method,
                    'ice_number' => $this->seller->ice_number,
                    'phone_number' => $this->seller->phone_number,
                    'address' => $this->seller->address,
                ]
            ),

            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),

            'total_orders_count' => (int) $this->total_orders_count,
            'delivered_amount' => (float) $this->delivered_amount,
            'returned_amount' => (float) $this->returned_amount,
            'delivery_fees_total' => (float) $this->delivery_fees_total,
            'return_fees_total' => (float) $this->return_fees_total,
            'gross_amount' => (float) $this->gross_amount,
            'net_amount' => (float) $this->net_amount,

            'generated_at' => $this->generated_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_by' => $this->whenLoaded('paidBy', fn () => $this->paidBy
                ? UserSummaryResource::make($this->paidBy)->resolve($request)
                : null),

            'payment_receipt_url' => $this->payment_receipt_attachment
                ? '/storage/'.ltrim($this->payment_receipt_attachment, '/')
                : null,
            'pdf_url' => $this->pdf_file ? route('invoices.pdf', $this->id) : null,

            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy
                ? UserSummaryResource::make($this->createdBy)->resolve($request)
                : null),
            'created_at' => $this->created_at?->toIso8601String(),

            'lines' => $this->whenLoaded('invoiceOrders', fn () => $this->invoiceOrders->map(function ($line) {
                $order = $line->relationLoaded('order') ? $line->order : null;
                $lineStatus = InvoiceStatus::tryFrom((string) $line->order_status_at_invoice);

                return [
                    'id' => $line->id,
                    'order_id' => $line->order_id,
                    'tracking_number' => $order?->tracking_number,
                    'customer_full_name' => $order?->customer_full_name,
                    'city' => $order?->city?->name,
                    'sector' => $order?->sector?->name,
                    'order_amount' => (float) $line->order_amount,
                    'delivery_fee' => (float) $line->delivery_fee,
                    'return_fee' => (float) $line->return_fee,
                    'final_amount' => (float) $line->final_amount,
                    'order_status_at_invoice' => $line->order_status_at_invoice,
                    'order_status_label' => $lineStatus?->label() ?? \App\Enums\OrderStatus::tryFrom((string) $line->order_status_at_invoice)?->label(),
                    // Delivered or returned on: snapshotted when the line was
                    // billed, with a fallback for invoices generated before the
                    // column existed and never backfilled.
                    'completed_at' => ($line->order_completed_at ?? $order?->completedAt())?->toIso8601String(),
                ];
            })->values()->all()),

            'logs' => $this->whenLoaded('logs', fn () => $this->logs->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'user' => $log->relationLoaded('user') && $log->user
                    ? UserSummaryResource::make($log->user)->resolve($request)
                    : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
