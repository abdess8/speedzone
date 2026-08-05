<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAutomaticInvoicesJob;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RunDailyBillingCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'billing:run-daily
        {--date= : Run as if today were this date (Y-m-d), useful for testing}
        {--sync : Run the job inline instead of dispatching to the queue}';

    /**
     * @var string
     */
    protected $description = 'Generate automatic seller invoices for every seller whose billing date has arrived.';

    public function handle(): int
    {
        $asOf = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))
            : CarbonImmutable::now();

        $this->info("Running daily billing as of {$asOf->toDateString()}...");

        $job = new GenerateAutomaticInvoicesJob($asOf);

        if ($this->option('sync')) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }

        $this->info('Daily billing dispatched.');

        return self::SUCCESS;
    }
}
