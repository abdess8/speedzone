<?php

namespace App\Jobs;

use App\Enums\BillingFrequency;
use App\Models\Role;
use App\Models\User;
use App\Services\InvoiceGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily automatic billing. For every seller whose automatic billing is enabled
 * and whose next_billing_date has arrived, generate an invoice for all
 * outstanding (delivered/returned, not yet invoiced) orders and roll the
 * next_billing_date forward according to their frequency.
 */
class GenerateAutomaticInvoicesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly ?CarbonImmutable $asOf = null) {}

    public function handle(InvoiceGeneratorService $generator): void
    {
        $asOf = $this->asOf ?? CarbonImmutable::now();

        User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::SELLER))
            ->where('billing_enabled', true)
            ->whereNotNull('next_billing_date')
            ->whereDate('next_billing_date', '<=', $asOf->toDateString())
            ->whereNot('billing_frequency', BillingFrequency::CUSTOM->value)
            ->orderBy('id')
            ->chunkById(100, function ($sellers) use ($generator, $asOf) {
                foreach ($sellers as $seller) {
                    $this->processSeller($generator, $seller, $asOf);
                }
            });
    }

    private function processSeller(InvoiceGeneratorService $generator, User $seller, CarbonImmutable $asOf): void
    {
        try {
            // Automatic runs bill everything outstanding (no period filter),
            // one invoice per store.
            $invoices = $generator->generateForSeller($seller, null, null, null);

            $this->advanceNextBillingDate($seller, $asOf);

            foreach ($invoices as $invoice) {
                Log::info('Automatic invoice generated', [
                    'seller_id' => $seller->id,
                    'store_id' => $invoice->store_id,
                    'invoice_number' => $invoice->invoice_number,
                    'orders' => $invoice->total_orders_count,
                    'net_amount' => $invoice->net_amount,
                ]);
            }
        } catch (Throwable $e) {
            // Never let one seller's failure abort the whole run.
            Log::error('Automatic invoice generation failed', [
                'seller_id' => $seller->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Roll next_billing_date forward, skipping past any missed cycles so it lands
     * strictly in the future relative to the run date.
     */
    private function advanceNextBillingDate(User $seller, CarbonImmutable $asOf): void
    {
        $frequency = $seller->billing_frequency instanceof BillingFrequency
            ? $seller->billing_frequency
            : BillingFrequency::from((string) $seller->billing_frequency);

        $next = $seller->next_billing_date
            ? CarbonImmutable::parse($seller->next_billing_date)
            : $asOf;

        $guard = 0;
        do {
            $advanced = $frequency->nextDateFrom($next);
            if (! $advanced) {
                return; // custom — never auto-scheduled
            }
            $next = CarbonImmutable::parse($advanced);
            $guard++;
        } while ($next->lte($asOf) && $guard < 1000);

        $seller->forceFill(['next_billing_date' => $next->toDateString()])->save();
    }
}
