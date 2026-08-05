<?php

namespace App\Observers;

use App\Models\Order;

/**
 * Outbound partner status sync is handled synchronously before local status
 * changes (see PartnerOutboundSyncService). This observer is kept as a stub
 * for future side-effects on order updates.
 */
class OrderObserver
{
    public function updated(Order $order): void
    {
        //
    }
}
