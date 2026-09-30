<?php

namespace App\Services\Ecommerce\Shopify;

use App\Enums\EcommercePlatform;
use App\Enums\OrderStatus;
use App\Enums\ShopifyExportStatus;
use App\Models\EcommerceIntegration;
use App\Models\Order;
use App\Services\Ecommerce\EcommerceIntegrationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes a SpeedZone parcel status to Shopify using the seller's mapping.
 *
 * Failures are logged and never block the local warehouse transition.
 */
class ShopifyOutboundStatusService
{
    public function __construct(
        private readonly ShopifyClient $client,
        private readonly EcommerceIntegrationService $integrations,
    ) {}

    public function push(Order $order, string $speedzoneStatus): void
    {
        $integration = $this->integration($order);

        if ($integration === null) {
            return;
        }

        $target = $this->mappedStatus($integration, $speedzoneStatus);

        if ($target === null) {
            return;
        }

        try {
            $token = $this->integrations->shopifyAccessToken($integration);
            $domain = (string) $integration->shop_slug;
            $shopifyOrderId = (string) $order->external_order_id;

            match ($target) {
                ShopifyExportStatus::Fulfilled => $this->fulfill($domain, $token, $order, $shopifyOrderId),
                ShopifyExportStatus::Cancelled => $this->client->cancelOrder($domain, $token, $shopifyOrderId, [
                    'reason' => 'customer',
                    'email' => false,
                    'restock' => $speedzoneStatus !== 'DELIVERED',
                ]),
                ShopifyExportStatus::Closed => $this->client->closeOrder($domain, $token, $shopifyOrderId),
                ShopifyExportStatus::Open => $this->client->openOrder($domain, $token, $shopifyOrderId),
                default => $this->trackingEvent($domain, $token, $order, $shopifyOrderId, $target),
            };
        } catch (Throwable $e) {
            Log::warning('Shopify outbound status push failed.', [
                'order_id' => $order->id,
                'shopify_order_id' => $order->external_order_id,
                'speedzone_status' => $speedzoneStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Push the current SpeedZone status of every mapped parcel for this shop.
     * Pass `$since` on scheduled runs so unchanged parcels are skipped.
     */
    public function pushForIntegration(EcommerceIntegration $integration, ?Carbon $since = null): int
    {
        if ($integration->platform !== EcommercePlatform::Shopify || ! $integration->isConnected()) {
            return 0;
        }

        $mappedStatuses = [];

        foreach ($integration->status_mapping ?? [] as $speedzone => $shopify) {
            if (is_string($speedzone) && is_string($shopify) && $shopify !== '' && OrderStatus::tryFrom($speedzone)) {
                $mappedStatuses[] = $speedzone;
            }
        }

        if ($mappedStatuses === []) {
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

            $this->push($order, $status);
            $pushed++;
        }

        return $pushed;
    }

    public function mappedStatus(EcommerceIntegration $integration, string $speedzoneStatus): ?ShopifyExportStatus
    {
        $mapped = $integration->status_mapping[$speedzoneStatus] ?? null;

        if (! is_string($mapped) || $mapped === '') {
            return null;
        }

        return ShopifyExportStatus::tryFrom($mapped);
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
            || $integration->platform !== EcommercePlatform::Shopify
            || ! $integration->isConnected()) {
            return null;
        }

        return $integration;
    }

    private function fulfill(string $domain, string $token, Order $order, string $shopifyOrderId): ?string
    {
        $open = $this->openFulfillmentOrders($domain, $token, $shopifyOrderId);

        if ($open === []) {
            return $this->latestFulfillmentId($domain, $token, $shopifyOrderId);
        }

        $created = $this->client->createFulfillment($domain, $token, [
            'notify_customer' => false,
            'tracking_info' => $this->trackingInfo($order),
            'line_items_by_fulfillment_order' => array_map(
                static fn (array $fulfillmentOrder): array => [
                    'fulfillment_order_id' => $fulfillmentOrder['id'],
                ],
                $open,
            ),
        ]);

        if (isset($created['id'])) {
            return (string) $created['id'];
        }

        return $this->latestFulfillmentId($domain, $token, $shopifyOrderId);
    }

    private function trackingEvent(
        string $domain,
        string $token,
        Order $order,
        string $shopifyOrderId,
        ShopifyExportStatus $status,
    ): void {
        $fulfillmentId = $this->ensureFulfillment($domain, $token, $order, $shopifyOrderId);

        if ($fulfillmentId === null) {
            return;
        }

        $this->client->createFulfillmentEvent(
            $domain,
            $token,
            $shopifyOrderId,
            $fulfillmentId,
            $status->value,
        );
    }

    private function ensureFulfillment(string $domain, string $token, Order $order, string $shopifyOrderId): ?string
    {
        return $this->latestFulfillmentId($domain, $token, $shopifyOrderId)
            ?? $this->fulfill($domain, $token, $order, $shopifyOrderId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openFulfillmentOrders(string $domain, string $token, string $shopifyOrderId): array
    {
        return array_values(array_filter(
            $this->client->fulfillmentOrders($domain, $token, $shopifyOrderId),
            static fn (mixed $fulfillmentOrder): bool => is_array($fulfillmentOrder)
                && isset($fulfillmentOrder['id'])
                && in_array($fulfillmentOrder['status'] ?? '', ['open', 'in_progress', 'scheduled'], true),
        ));
    }

    private function latestFulfillmentId(string $domain, string $token, string $shopifyOrderId): ?string
    {
        $fulfillments = $this->client->fulfillments($domain, $token, $shopifyOrderId);
        $latest = end($fulfillments);

        if (! is_array($latest) || blank($latest['id'] ?? null)) {
            return null;
        }

        return (string) $latest['id'];
    }

    /**
     * @return array{number: string, company: string, url: string}
     */
    private function trackingInfo(Order $order): array
    {
        return [
            'number' => (string) $order->tracking_number,
            'company' => 'SpeedZone Express',
            'url' => $order->trackingUrl(),
        ];
    }
}
