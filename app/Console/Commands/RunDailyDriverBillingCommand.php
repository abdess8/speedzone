<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDriverInvoicesJob;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RunDailyDriverBillingCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'driver-billing:run-daily
        {--date= : Run as if today were this date (Y-m-d), useful for testing}
        {--sync : Run the job inline instead of dispatching to the queue}';

    /**
     * @var string
     */
    protected $description = 'Generate automatic driver invoices for every driver whose billing date has arrived.';

    public function handle(): int
    {
        $asOf = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))
            : CarbonImmutable::now();

        $this->info("Running daily driver billing as of {$asOf->toDateString()}...");

        $job = new GenerateDriverInvoicesJob($asOf);

        if ($this->option('sync')) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }

        $this->info('Daily driver billing dispatched.');

        return self::SUCCESS;
    }
}
