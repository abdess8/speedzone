<?php

namespace App\Support;

use App\Enums\EcommerceIntegrationStatus;
use App\Models\EcommerceIntegration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Runs due storefront auto-syncs after a web request.
 *
 * The reliable trigger remains `php artisan schedule:run` (cron) /
 * `ecommerce:sync-due`. Local `artisan serve` never fires that, so without
 * this heartbeat the toggle appears broken: next_sync_at sits in the past
 * and nothing ever picks it up.
 */
final class EcommerceSyncHeartbeat
{
    public static function tick(): void
    {
        if (! Cache::add('ecommerce.sync-due.heartbeat', 1, 60)) {
            return;
        }

        $due = EcommerceIntegration::query()
            ->where('status', EcommerceIntegrationStatus::Connected->value)
            ->where('auto_sync_enabled', true)
            ->whereNotNull('next_sync_at')
            ->where('next_sync_at', '<=', now())
            ->exists();

        if (! $due) {
            return;
        }

        Artisan::call('ecommerce:sync-due');
    }
}
