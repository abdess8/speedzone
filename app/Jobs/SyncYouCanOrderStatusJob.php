<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Ecommerce\YouCan\YouCanOutboundStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Apply a SpeedZone parcel status to the linked YouCan order.
 */
class SyncYouCanOrderStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $orderId,
        public readonly string $targetStatus,
    ) {}

    public function handle(YouCanOutboundStatusService $sync): void
    {
        $order = Order::query()
            ->with('ecommerceIntegration')
            ->find($this->orderId);

        if ($order === null) {
            return;
        }

        $sync->push($order, $this->targetStatus);
    }
}
