<?php

namespace App\Observers;

use App\Jobs\SyncPartnerOrderStatusJob;
use App\Models\Order;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->suppressPartnerStatusSync) {
            return;
        }

        if (! $order->partner_id || ! $order->wasChanged('status')) {
            return;
        }

        $order->loadMissing('partner');

        if (! $order->partner?->sync_status) {
            return;
        }

        SyncPartnerOrderStatusJob::dispatch($order->id);
    }
}
