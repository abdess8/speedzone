<?php

namespace App\Observers;

use App\Enums\EcommercePlatform;
use App\Enums\OrderStatus;
use App\Jobs\SyncShopifyOrderStatusJob;
use App\Jobs\SyncYouCanOrderStatusJob;
use App\Models\Order;

/**
 * Partner outbound sync stays synchronous in OrderTransitionService.
 * Storefront status mapping is applied after the local change so a
 * Shopify / YouCan outage never blocks a depot scan.
 */
class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')
            || ! $order->ecommerce_integration_id
            || blank($order->external_order_id)) {
            return;
        }

        $status = $order->status instanceof OrderStatus
            ? $order->status->value
            : (string) $order->status;

        $order->loadMissing('ecommerceIntegration');
        $platform = $order->ecommerceIntegration?->platform;

        match ($platform) {
            EcommercePlatform::Shopify => SyncShopifyOrderStatusJob::dispatch($order->id, $status),
            EcommercePlatform::YouCan => SyncYouCanOrderStatusJob::dispatch($order->id, $status),
            default => null,
        };
    }
}
