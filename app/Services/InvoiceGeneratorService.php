<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Events\InvoiceGenerated;
use App\Models\Invoice;
use App\Models\InvoiceLog;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Orchestrates the lifecycle of an invoice: generation (with order snapshots),
 * payment, cancellation and deletion. Every mutation is transactional and
 * audited through invoice_logs.
 */
class InvoiceGeneratorService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly PdfInvoiceService $pdf,
    ) {}

    /**
     * Generate an invoice for a seller over an optional period.
     *
     * Returns null when there are no billable orders (nothing is created).
     */
    public function generate(
        User $seller,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        ?User $createdBy = null,
    ): ?Invoice {
        $invoice = DB::transaction(function () use ($seller, $start, $end, $createdBy): ?Invoice {
            // Lock the candidate orders so two concurrent runs can't double-bill.
            $orders = $this->billing->billableOrdersQuery($seller, $start, $end)
                ->with('sector')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return null;
            }

            $summary = $this->billing->summarize($orders);

            $invoice = Invoice::create([
                'invoice_number' => 'PENDING',
                'seller_id' => $seller->id,
                'period_start' => $start?->toDateString(),
                'period_end' => $end?->toDateString(),
                'total_orders_count' => $summary['total_orders_count'],
                'delivered_amount' => $summary['delivered_amount'],
                'returned_amount' => $summary['returned_amount'],
                'delivery_fees_total' => $summary['delivery_fees_total'],
                'return_fees_total' => $summary['return_fees_total'],
                'gross_amount' => $summary['gross_amount'],
                'net_amount' => $summary['net_amount'],
                'status' => InvoiceStatus::GENERATED->value,
                'generated_at' => now(),
                'created_by' => $createdBy?->id,
            ]);

            $invoice->forceFill(['invoice_number' => $this->makeNumber($invoice)])->save();

            foreach ($orders as $order) {
                $line = $this->billing->computeLine($order);

                $invoice->invoiceOrders()->create([
                    'order_id' => $order->id,
                    'order_amount' => $line['order_amount'],
                    'delivery_fee' => $line['delivery_fee'],
                    'return_fee' => $line['return_fee'],
                    'final_amount' => $line['final_amount'],
                    'order_status_at_invoice' => $line['status'],
                ]);

                $order->forceFill([
                    'invoice_id' => $invoice->id,
                    'invoice_status' => InvoiceStatus::GENERATED->value,
                ])->save();
            }

            $invoice->log(InvoiceLog::ACTION_CREATED, $createdBy, null, [
                'invoice_number' => $invoice->invoice_number,
                'orders' => $summary['total_orders_count'],
                'net_amount' => $summary['net_amount'],
            ]);

            return $invoice;
        });

        if ($invoice) {
            // PDF generation is outside the DB transaction (filesystem side effect).
            $this->pdf->store($invoice);

            event(new InvoiceGenerated($invoice->fresh(), $createdBy));
        }

        return $invoice?->fresh();
    }

    /**
     * Mark a generated/sent invoice as paid. Receipt + payment date are required.
     */
    public function markPaid(Invoice $invoice, User $admin, CarbonInterface $paidAt, string $receiptPath): Invoice
    {
        $this->assertStatus($invoice, [InvoiceStatus::GENERATED, InvoiceStatus::SENT]);

        return DB::transaction(function () use ($invoice, $admin, $paidAt, $receiptPath): Invoice {
            $old = $invoice->status;

            $invoice->forceFill([
                'status' => InvoiceStatus::PAID->value,
                'paid_at' => $paidAt,
                'paid_by' => $admin->id,
                'payment_receipt_attachment' => $receiptPath,
            ])->save();

            $invoice->orders()->update(['invoice_status' => InvoiceStatus::PAID->value]);

            $invoice->log(InvoiceLog::ACTION_PAID, $admin, $old, [
                'status' => InvoiceStatus::PAID->value,
                'paid_at' => $paidAt->toDateTimeString(),
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Optionally flag a generated invoice as sent to the seller.
     */
    public function markSent(Invoice $invoice, User $admin): Invoice
    {
        $this->assertStatus($invoice, [InvoiceStatus::GENERATED]);

        return DB::transaction(function () use ($invoice, $admin): Invoice {
            $old = $invoice->status;

            $invoice->forceFill(['status' => InvoiceStatus::SENT->value])->save();
            $invoice->orders()->update(['invoice_status' => InvoiceStatus::SENT->value]);
            $invoice->log(InvoiceLog::ACTION_STATUS_CHANGED, $admin, $old, InvoiceStatus::SENT->value);

            return $invoice->fresh();
        });
    }

    /**
     * Cancel an invoice and release its orders so they can be re-billed.
     */
    public function cancel(Invoice $invoice, User $admin): Invoice
    {
        $this->assertStatus($invoice, [InvoiceStatus::GENERATED, InvoiceStatus::SENT]);

        return DB::transaction(function () use ($invoice, $admin): Invoice {
            $old = $invoice->status;

            $invoice->orders()->update([
                'invoice_id' => null,
                'invoice_status' => null,
            ]);
            $invoice->invoiceOrders()->delete();

            $invoice->forceFill(['status' => InvoiceStatus::CANCELLED->value])->save();

            $invoice->log(InvoiceLog::ACTION_CANCELLED, $admin, $old, InvoiceStatus::CANCELLED->value);

            return $invoice->fresh();
        });
    }

    /**
     * Permanently delete a cancelled invoice (orders are already released).
     */
    public function delete(Invoice $invoice, User $admin): void
    {
        $this->assertStatus($invoice, [InvoiceStatus::CANCELLED]);

        DB::transaction(function () use ($invoice, $admin): void {
            // invoice_logs cascade on delete, so the deletion is mirrored to the
            // application log for a durable audit record.
            Log::info('Invoice deleted', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'deleted_by' => $admin->id,
            ]);

            if ($invoice->pdf_file) {
                Storage::disk('public')->delete($invoice->pdf_file);
            }

            $invoice->delete();
        });
    }

    /**
     * Build a unique, human-readable invoice number from the row id.
     */
    private function makeNumber(Invoice $invoice): string
    {
        $year = ($invoice->generated_at ?? now())->year;

        return sprintf('INV-%d-%06d', $year, $invoice->id);
    }

    /**
     * @param  array<int, InvoiceStatus>  $allowed
     */
    private function assertStatus(Invoice $invoice, array $allowed): void
    {
        $status = $invoice->status instanceof InvoiceStatus
            ? $invoice->status
            : InvoiceStatus::from($invoice->status);

        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException(
                "Invoice {$invoice->invoice_number} is {$status->value}; this action is not allowed."
            );
        }
    }
}
