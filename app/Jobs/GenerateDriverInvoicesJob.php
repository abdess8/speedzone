<?php

namespace App\Jobs;

use App\Enums\BillingFrequency;
use App\Models\Role;
use App\Models\User;
use App\Services\DriverInvoiceGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily automatic driver billing. For every driver whose automatic billing is
 * enabled and whose next_billing_date has arrived, generate an invoice for all
 * outstanding (confirmed, not yet invoiced) transactions and roll the
 * next_billing_date forward according to their frequency.
 */
class GenerateDriverInvoicesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly ?CarbonImmutable $asOf = null) {}

    public function handle(DriverInvoiceGeneratorService $generator): void
    {
        $asOf = $this->asOf ?? CarbonImmutable::now();

        User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::DRIVER))
            ->where('billing_enabled', true)
            ->whereNotNull('next_billing_date')
            ->whereDate('next_billing_date', '<=', $asOf->toDateString())
            ->whereNot('billing_frequency', BillingFrequency::CUSTOM->value)
            ->orderBy('id')
            ->chunkById(100, function ($drivers) use ($generator, $asOf) {
                foreach ($drivers as $driver) {
                    $this->processDriver($generator, $driver, $asOf);
                }
            });
    }

    private function processDriver(DriverInvoiceGeneratorService $generator, User $driver, CarbonImmutable $asOf): void
    {
        try {
            $invoice = $generator->generate($driver, null, null, null);

            $this->advanceNextBillingDate($driver, $asOf);

            if ($invoice) {
                Log::info('Automatic driver invoice generated', [
                    'driver_id' => $driver->id,
                    'invoice_number' => $invoice->invoice_number,
                    'deliveries' => $invoice->deliveries_count,
                    'total_amount' => $invoice->total_amount,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Automatic driver invoice generation failed', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Roll next_billing_date forward, skipping past any missed cycles so it lands
     * strictly in the future relative to the run date.
     */
    private function advanceNextBillingDate(User $driver, CarbonImmutable $asOf): void
    {
        $frequency = $driver->billing_frequency instanceof BillingFrequency
            ? $driver->billing_frequency
            : BillingFrequency::from((string) $driver->billing_frequency);

        $next = $driver->next_billing_date
            ? CarbonImmutable::parse($driver->next_billing_date)
            : $asOf;

        $guard = 0;
        do {
            $advanced = $frequency->nextDateFrom($next);
            if (! $advanced) {
                return;
            }
            $next = CarbonImmutable::parse($advanced);
            $guard++;
        } while ($next->lte($asOf) && $guard < 1000);

        $driver->forceFill(['next_billing_date' => $next->toDateString()])->save();
    }
}
