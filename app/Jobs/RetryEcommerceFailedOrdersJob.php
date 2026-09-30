<?php

namespace App\Jobs;

use App\Enums\EcommerceSyncStatus;
use App\Models\EcommerceIntegration;
use App\Models\EcommerceIntegrationSync;
use App\Services\Ecommerce\EcommerceOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryEcommerceFailedOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $integrationId,
        public readonly int $syncId,
    ) {}

    public function uniqueId(): string
    {
        return 'ecommerce-sync-'.$this->integrationId;
    }

    public function handle(EcommerceOrderSyncService $syncs): void
    {
        $integration = EcommerceIntegration::query()->find($this->integrationId);
        $sync = EcommerceIntegrationSync::query()->find($this->syncId);

        if ($integration === null || $sync === null) {
            return;
        }

        if ((int) $sync->ecommerce_integration_id !== (int) $integration->id) {
            return;
        }

        if ($integration->syncs()->where('status', EcommerceSyncStatus::Running->value)->exists()) {
            return;
        }

        $syncs->retryFailed($sync);
    }
}
