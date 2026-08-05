<?php

namespace App\Services;

use App\Enums\DriverInvoiceStatus;
use App\Enums\DriverTransactionStatus;
use App\Models\DriverFinanceLog;
use App\Models\DriverInvoice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the lifecycle of a driver settlement invoice: generation (with
 * transaction snapshots), payment, cancellation and deletion. Every mutation is
 * transactional and audited through driver_finance_logs.
 */
class DriverInvoiceGeneratorService
{
    public function __construct(
        private readonly DriverBillingService $billing,
        private readonly DriverPdfService $pdf,
    ) {}

    /**
     * Generate an invoice for a driver over an optional period.
     *
     * Returns null when there are no billable transactions (nothing is created).
     */
    public function generate(
        User $driver,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        ?User $createdBy = null,
    ): ?DriverInvoice {
        $invoice = DB::transaction(function () use ($driver, $start, $end, $createdBy): ?DriverInvoice {
            // Lock candidate transactions so two concurrent runs can't double-bill.
            $transactions = $this->billing->billableTransactionsQuery($driver, $start, $end)
                ->with(['order', 'sector'])
                ->lockForUpdate()
                ->get();

            if ($transactions->isEmpty()) {
                return null;
            }

            $summary = $this->billing->summarize($transactions);

            $invoice = DriverInvoice::create([
                'invoice_number' => 'PENDING',
                'driver_id' => $driver->id,
                'period_start' => $start?->toDateString(),
                'period_end' => $end?->toDateString(),
                'deliveries_count' => $summary['deliveries_count'],
                'total_amount' => $summary['total_amount'],
                'status' => DriverInvoiceStatus::GENERATED->value,
                'generated_at' => now(),
                'created_by' => $createdBy?->id,
            ]);

            $invoice->forceFill(['invoice_number' => $this->makeNumber($invoice)])->save();

            foreach ($transactions as $transaction) {
                $invoice->invoiceTransactions()->create([
                    'driver_transaction_id' => $transaction->id,
                    'amount_snapshot' => round((float) $transaction->amount, 2),
                ]);

                // Lock the transaction to this invoice. It can no longer be edited.
                $transaction->forceFill(['driver_invoice_id' => $invoice->id])->save();
            }

            $invoice->log(DriverFinanceLog::ACTION_INVOICE_CREATED, $createdBy, null, [
                'invoice_number' => $invoice->invoice_number,
                'deliveries' => $summary['deliveries_count'],
                'total_amount' => $summary['total_amount'],
            ]);

            return $invoice;
        });

        if ($invoice) {
            // PDF generation is outside the DB transaction (filesystem side effect).
            $this->pdf->store($invoice);
        }

        return $invoice?->fresh();
    }

    /**
     * Mark a generated invoice as paid. Receipt + payment date are required.
     */
    public function markPaid(DriverInvoice $invoice, User $admin, CarbonInterface $paidAt, string $receiptPath): DriverInvoice
    {
        $this->assertStatus($invoice, [DriverInvoiceStatus::GENERATED]);

        return DB::transaction(function () use ($invoice, $admin, $paidAt, $receiptPath): DriverInvoice {
            $old = $invoice->status;

            $invoice->forceFill([
                'status' => DriverInvoiceStatus::PAID->value,
                'paid_at' => $paidAt,
                'paid_by' => $admin->id,
                'payment_receipt_attachment' => $receiptPath,
            ])->save();

            // The transactions are now settled and frozen.
            $invoice->transactions()->update(['status' => DriverTransactionStatus::PAID->value]);

            $invoice->log(DriverFinanceLog::ACTION_INVOICE_PAID, $admin, $old, [
                'status' => DriverInvoiceStatus::PAID->value,
                'paid_at' => $paidAt->toDateTimeString(),
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Cancel an invoice and release its transactions so they can be re-billed.
     */
    public function cancel(DriverInvoice $invoice, User $admin): DriverInvoice
    {
        $this->assertStatus($invoice, [DriverInvoiceStatus::GENERATED]);

        return DB::transaction(function () use ($invoice, $admin): DriverInvoice {
            $old = $invoice->status;

            $invoice->transactions()->update([
                'driver_invoice_id' => null,
                'status' => DriverTransactionStatus::CONFIRMED->value,
            ]);
            $invoice->invoiceTransactions()->delete();

            $invoice->forceFill(['status' => DriverInvoiceStatus::CANCELLED->value])->save();

            $invoice->log(DriverFinanceLog::ACTION_INVOICE_CANCELLED, $admin, $old, DriverInvoiceStatus::CANCELLED->value);

            return $invoice->fresh();
        });
    }

    /**
     * Permanently delete a cancelled invoice (transactions already released).
     */
    public function delete(DriverInvoice $invoice, User $admin): void
    {
        $this->assertStatus($invoice, [DriverInvoiceStatus::CANCELLED]);

        DB::transaction(function () use ($invoice, $admin): void {
            Log::info('Driver invoice deleted', [
                'driver_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'deleted_by' => $admin->id,
            ]);

            $invoice->log(DriverFinanceLog::ACTION_INVOICE_DELETED, $admin, $invoice->invoice_number, null);

            if ($invoice->pdf_file) {
                Storage::disk('public')->delete($invoice->pdf_file);
            }

            // Detach finance logs from the invoice so the audit trail survives.
            $invoice->logs()->update(['driver_invoice_id' => null]);

            $invoice->delete();
        });
    }

    /**
     * Build a unique, human-readable invoice number from the row id.
     */
    private function makeNumber(DriverInvoice $invoice): string
    {
        $year = ($invoice->generated_at ?? now())->year;

        return sprintf('DRV-%d-%06d', $year, $invoice->id);
    }

    /**
     * @param  array<int, DriverInvoiceStatus>  $allowed
     */
    private function assertStatus(DriverInvoice $invoice, array $allowed): void
    {
        $status = $invoice->status instanceof DriverInvoiceStatus
            ? $invoice->status
            : DriverInvoiceStatus::from($invoice->status);

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('driver_invoices.errors.invalid_status', [
                    'number' => $invoice->invoice_number,
                    'status' => $status->label(),
                ]),
            ]);
        }
    }
}
