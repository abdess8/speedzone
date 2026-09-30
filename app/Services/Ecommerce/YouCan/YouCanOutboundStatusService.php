<?php

namespace App\Services\Ecommerce\YouCan;

use App\Enums\EcommercePlatform;
use App\Enums\OrderStatus;
use App\Enums\YouCanExportStatus;
use App\Models\EcommerceIntegration;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes a SpeedZone parcel status to YouCan using the seller's mapping.
 *
 * Failures are logged and never block the local warehouse transition.
 */
class YouCanOutboundStatusService
{
    public function __construct(
        private readonly YouCanClient $client,
    ) {}

    public function push(Order $order, string $speedzoneStatus): void
    {
        $integration = $this->integration($order);

        if ($integration === null) {
            return;
        }

        $target = $this->mappedStatus($integration, $speedzoneStatus);

        if ($target === null || ! $this->authenticate($integration)) {
            return;
        }

        $this->send($order, $target);
    }

    /**
     * Push the current SpeedZone status of every mapped parcel for this shop.
     * Pass `$since` on scheduled runs so unchanged parcels are skipped.
     */
    public function pushForIntegration(EcommerceIntegration $integration, ?Carbon $since = null): int
    {
        if ($integration->platform !== EcommercePlatform::YouCan || ! $integration->isConnected()) {
            return 0;
        }

        $mappedStatuses = [];

        foreach ($integration->status_mapping ?? [] as $speedzone => $youCan) {
            if (is_string($speedzone) && is_string($youCan) && $youCan !== '' && OrderStatus::tryFrom($speedzone)) {
                $mappedStatuses[] = $speedzone;
            }
        }

        if ($mappedStatuses === [] || ! $this->authenticate($integration)) {
            return 0;
        }

        $query = Order::query()
            ->with('ecommerceIntegration')
            ->where('ecommerce_integration_id', $integration->id)
            ->whereNotNull('external_order_id')
            ->whereIn('status', $mappedStatuses);

        if ($since !== null) {
            $query->where(function ($query) use ($since): void {
                $query->where('updated_at', '>=', $since)
                    ->orWhereHas(
                        'statusHistories',
                        fn ($history) => $history->where('created_at', '>=', $since)
                    );
            });
        }

        $pushed = 0;

        foreach ($query->cursor() as $order) {
            $status = $order->status instanceof OrderStatus
                ? $order->status->value
                : (string) $order->status;

            $target = $this->mappedStatus($integration, $status);

            if ($target === null) {
                continue;
            }

            $this->send($order, $target);
            $pushed++;
        }

        return $pushed;
    }

    public function mappedStatus(EcommerceIntegration $integration, string $speedzoneStatus): ?YouCanExportStatus
    {
        $mapped = $integration->status_mapping[$speedzoneStatus] ?? null;

        if (! is_string($mapped) || $mapped === '') {
            return null;
        }

        return YouCanExportStatus::tryFrom($mapped);
    }

    private function send(Order $order, YouCanExportStatus $target): void
    {
        try {
            $this->client->updateOrderStatus(
                (string) $order->external_order_id,
                $target,
            );
        } catch (Throwable $e) {
            Log::warning('YouCan outbound status push failed.', [
                'order_id' => $order->id,
                'youcan_order_id' => $order->external_order_id,
                'target' => $target->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function authenticate(EcommerceIntegration $integration): bool
    {
        $email = (string) $integration->email;
        $password = (string) $integration->client_secret;
        $storeId = (string) $integration->external_store_id;

        if ($email === '' || $password === '' || $storeId === '') {
            return false;
        }

        try {
            $this->client->authenticateForStore($email, $password, $storeId);

            return true;
        } catch (Throwable $e) {
            Log::warning('YouCan outbound authentication failed.', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function integration(Order $order): ?EcommerceIntegration
    {
        if (! $order->ecommerce_integration_id || blank($order->external_order_id)) {
            return null;
        }

        $integration = $order->relationLoaded('ecommerceIntegration')
            ? $order->ecommerceIntegration
            : $order->ecommerceIntegration()->first();

        if (! $integration instanceof EcommerceIntegration
            || $integration->platform !== EcommercePlatform::YouCan
            || ! $integration->isConnected()) {
            return null;
        }

        return $integration;
    }
}
