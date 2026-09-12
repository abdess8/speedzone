<?php

namespace App\Console\Commands;

use App\Enums\EcommerceIntegrationStatus;
use App\Enums\EcommerceSyncStatus;
use App\Enums\EcommerceSyncTrigger;
use App\Jobs\SyncEcommerceOrdersJob;
use App\Models\EcommerceIntegration;
use Illuminate\Console\Command;

class SyncDueEcommerceOrdersCommand extends Command
{
    protected $signature = 'ecommerce:sync-due
        {--sync : Run each due integration inline instead of dispatching to the queue}';

    protected $description = 'Pull new storefront orders for every connected shop whose auto-sync is due.';

    public function handle(): int
    {
        $due = EcommerceIntegration::query()
            ->where('status', EcommerceIntegrationStatus::Connected->value)
            ->where('auto_sync_enabled', true)
            ->whereNotNull('next_sync_at')
            ->where('next_sync_at', '<=', now())
            ->orderBy('id')
            ->get();

        $dispatched = 0;

        foreach ($due as $integration) {
            if ($integration->syncs()->where('status', EcommerceSyncStatus::Running->value)->exists()) {
                $this->line("Skipping integration {$integration->id}: a sync is already running.");

                continue;
            }

            $job = new SyncEcommerceOrdersJob($integration->id, EcommerceSyncTrigger::Schedule);

            if ($this->option('sync')) {
                dispatch_sync($job);
            } else {
                dispatch($job);
            }

            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} e-commerce sync(s).");

        return self::SUCCESS;
    }
}
