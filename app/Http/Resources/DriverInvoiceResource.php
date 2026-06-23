<?php

namespace App\Http\Resources;

use App\Enums\DriverInvoiceStatus;
use App\Enums\DriverTransactionType;
use App\Models\DriverInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DriverInvoice
 */
class DriverInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof DriverInvoiceStatus ? $this->status : DriverInvoiceStatus::from($this->status);

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,

            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'status_icon' => $status->icon(),
            'is_locked' => $status->isLocked(),

            'driver' => $this->whenLoaded('driver', fn () => $this->driver
                ? UserSummaryResource::make($this->driver)->resolve($request)
                : null),
            'driver_id' => $this->driver_id,

            'driver_billing' => $this->when(
                $this->relationLoaded('driver') && $this->driver,
                fn () => [
                    'bank_name' => $this->driver->bank_name,
                    'rib' => $this->driver->rib,
                    'payment_method' => $this->driver->payment_method instanceof \BackedEnum
                        ? $this->driver->payment_method->value
                        : $this->driver->payment_method,
                    'phone_number' => $this->driver->phone_number,
                    'cin' => $this->driver->cin,
                    'address' => $this->driver->address,
                ]
            ),

            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),

            'deliveries_count' => (int) $this->deliveries_count,
            'total_amount' => (float) $this->total_amount,

            'generated_at' => $this->generated_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_by' => $this->whenLoaded('paidBy', fn () => $this->paidBy
                ? UserSummaryResource::make($this->paidBy)->resolve($request)
                : null),

            'payment_receipt_url' => $this->payment_receipt_attachment
                ? '/storage/'.ltrim($this->payment_receipt_attachment, '/')
                : null,
            'pdf_url' => $this->pdf_file ? route('driver-invoices.pdf', $this->id) : null,

            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy
                ? UserSummaryResource::make($this->createdBy)->resolve($request)
                : null),
            'created_at' => $this->created_at?->toIso8601String(),

            'lines' => $this->whenLoaded('invoiceTransactions', fn () => $this->invoiceTransactions->map(function ($pivot) {
                $tx = $pivot->relationLoaded('driverTransaction') ? $pivot->driverTransaction : $pivot->driverTransaction;
                $order = $tx?->order;
                $type = $tx?->transaction_type instanceof DriverTransactionType
                    ? $tx->transaction_type
                    : ($tx ? DriverTransactionType::tryFrom((string) $tx->transaction_type) : null);

                return [
                    'id' => $pivot->id,
                    'transaction_id' => $pivot->driver_transaction_id,
                    'order_id' => $order?->id,
                    'tracking_number' => $order?->tracking_number,
                    'customer_full_name' => $order?->customer_full_name,
                    'city' => $order?->city?->name,
                    'sector' => $tx?->sector?->name ?? $order?->sector?->name,
                    'transaction_type' => $type?->value,
                    'transaction_type_label' => $type?->label(),
                    'amount' => (float) $pivot->amount_snapshot,
                    'note' => $tx?->note,
                    'created_at' => $tx?->created_at?->toIso8601String(),
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
