<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Partners\PartnerApiException;
use App\Services\Partners\PartnerOutboundSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push a partner delivery status update to the partner API (e.g. Sendit /update-deliveries).
 */
class SyncPartnerOrderStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $orderId) {}

    public function handle(PartnerOutboundSyncService $sync): void
    {
        $order = Order::query()
            ->with('partner')
            ->find($this->orderId);

        if (! $order || ! $order->partner_id) {
            return;
        }

        $partner = $order->partner;

        if (! $partner || ! $partner->sync_status) {
            return;
        }

        try {
            $sync->pushOrderStatus($order);
        } catch (PartnerApiException $e) {
            Log::error('Partner outbound status sync failed.', [
                'order_id' => $order->id,
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            Log::error('Partner outbound status sync failed.', [
                'order_id' => $order->id,
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
