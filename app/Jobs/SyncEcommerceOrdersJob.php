<?php

namespace App\Jobs;

use App\Enums\EcommerceSyncStatus;
use App\Enums\EcommerceSyncTrigger;
use App\Models\EcommerceIntegration;
use App\Services\Ecommerce\EcommerceOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncEcommerceOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $integrationId,
        public readonly EcommerceSyncTrigger $trigger = EcommerceSyncTrigger::Schedule,
    ) {}

    public function uniqueId(): string
    {
        return 'ecommerce-sync-'.$this->integrationId;
    }

    public function handle(EcommerceOrderSyncService $syncs): void
    {
        $integration = EcommerceIntegration::query()->find($this->integrationId);

        if ($integration === null) {
            return;
        }

        if ($integration->syncs()->where('status', EcommerceSyncStatus::Running->value)->exists()) {
            return;
        }

        $syncs->run($integration, $this->trigger);
    }
}
