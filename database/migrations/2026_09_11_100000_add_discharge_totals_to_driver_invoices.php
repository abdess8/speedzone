<?php

use App\Models\DriverInvoice;
use App\Services\DriverBillingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * A driver invoice is a cash discharge: the driver remits what he collected
 * from customers, minus his delivery commission. Existing invoices stored only
 * the commission total, so we snapshot the collected / commission split and
 * rewrite the due amount from the linked orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_invoices', function (Blueprint $table): void {
            $table->decimal('collected_amount', 14, 2)->default(0)->after('deliveries_count');
            $table->decimal('commission_total', 14, 2)->default(0)->after('collected_amount');
        });

        Schema::table('driver_invoice_transactions', function (Blueprint $table): void {
            $table->decimal('collected_snapshot', 12, 2)->default(0)->after('driver_transaction_id');
            $table->decimal('commission_snapshot', 12, 2)->default(0)->after('collected_snapshot');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('driver_invoice_transactions', function (Blueprint $table): void {
            $table->dropColumn(['collected_snapshot', 'commission_snapshot']);
        });

        Schema::table('driver_invoices', function (Blueprint $table): void {
            $table->dropColumn(['collected_amount', 'commission_total']);
        });
    }

    private function backfill(): void
    {
        $billing = app(DriverBillingService::class);

        DriverInvoice::query()
            ->with(['invoiceTransactions.driverTransaction.order'])
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($billing): void {
                foreach ($invoices as $invoice) {
                    if ($invoice->invoiceTransactions->isEmpty()) {
                        continue;
                    }

                    $transactions = $invoice->invoiceTransactions
                        ->map(fn ($pivot) => $pivot->driverTransaction)
                        ->filter()
                        ->values();

                    foreach ($invoice->invoiceTransactions as $pivot) {
                        $transaction = $pivot->driverTransaction;
                        if (! $transaction) {
                            continue;
                        }

                        $line = $billing->computeLine($transaction);

                        $pivot->forceFill([
                            'collected_snapshot' => $line['collected_amount'],
                            'commission_snapshot' => $line['commission'],
                            'amount_snapshot' => $line['amount'],
                        ])->save();
                    }

                    $summary = $billing->summarize($transactions);

                    if ($invoice->pdf_file) {
                        Storage::disk('public')->delete($invoice->pdf_file);
                    }

                    $invoice->forceFill([
                        'collected_amount' => $summary['collected_amount'],
                        'commission_total' => $summary['commission_total'],
                        'total_amount' => $summary['total_amount'],
                        'deliveries_count' => $summary['deliveries_count'],
                        'pdf_file' => null,
                    ])->save();
                }
            });
    }
};
