<?php

namespace App\Services\Partners;

use App\Models\Order;
use App\Models\Partner;
use Illuminate\Support\Facades\Log;

/**
 * Builds outbound payloads and pushes partner delivery status updates.
 */
class PartnerOutboundSyncService
{
    public function __construct(
        private readonly PartnerApiService $partnerApi,
        private readonly PartnerStatusMapper $statusMapper,
    ) {}

    public function pushOrderStatus(Order $order): void
    {
        if (! $order->partner_id || ! $order->external_tracking_code) {
            return;
        }

        $partner = $order->relationLoaded('partner')
            ? $order->partner
            : Partner::query()->find($order->partner_id);

        if (! $partner || ! $partner->sync_status || ! $partner->is_active) {
            return;
        }

        $status = $order->status instanceof \App\Enums\OrderStatus
            ? $order->status
            : \App\Enums\OrderStatus::from($order->status);

        $partnerStatus = $this->statusMapper->toPartnerStatus($partner, $status);

        if ($partnerStatus === null) {
            Log::warning('Partner outbound sync skipped: no status mapping.', [
                'order_id' => $order->id,
                'partner_id' => $partner->id,
                'speedzone_status' => $status->value,
            ]);

            return;
        }

        $payload = [
            'deliveries' => [
                [
                    'code' => $order->external_tracking_code,
                    'status' => $partnerStatus,
                ],
            ],
        ];

        $this->partnerApi->pushStatusUpdate($partner, $payload);
    }
}
