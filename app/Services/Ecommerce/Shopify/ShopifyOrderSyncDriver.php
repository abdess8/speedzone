<?php

namespace App\Services\Ecommerce\Shopify;

use App\Enums\ShopifyImportStatus;
use App\Models\EcommerceIntegration;
use Illuminate\Support\Carbon;

class ShopifyOrderSyncDriver
{
    public function __construct(
        private readonly ShopifyClient $client,
        private readonly ShopifyOrderMapper $mapper,
    ) {}

    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function fetchOrders(EcommerceIntegration $integration, ?Carbon $since): iterable
    {
        $token = (string) $integration->access_token;
        $domain = (string) $integration->shop_slug;

        if ($token === '' || $domain === '') {
            throw new \RuntimeException(__('integrations.shopify.errors.credentials'));
        }

        $maxPages = max(1, (int) config('shopify.orders_max_pages', 10));
        $perPage = max(1, min(250, (int) config('shopify.orders_page_size', 50)));
        $handled = $this->mapper->handledCatalogKeys($integration);

        $query = [
            'limit' => $perPage,
            'status' => 'any',
            'order' => 'updated_at desc',
        ];

        if ($since !== null) {
            $query['updated_at_min'] = $since->toIso8601String();
        }

        $pageInfo = null;
        $page = 1;

        while ($page <= $maxPages) {
            $request = $pageInfo !== null
                ? ['limit' => $perPage, 'page_info' => $pageInfo]
                : $query;

            $chunk = $this->client->orders($domain, $token, $request);
            $caughtUp = $chunk['orders'] !== [];

            foreach ($chunk['orders'] as $order) {
                if (! is_array($order)) {
                    continue;
                }

                $externalId = $this->mapper->externalId($order);
                $createdAt = $this->mapper->createdAt($order);
                $olderThanWindow = $since !== null && $createdAt !== null && $createdAt->lt($since);
                $alreadyHandled = $this->mapper->isHandled(
                    $handled,
                    $externalId,
                    $this->mapper->orderRef($order),
                );

                if ($olderThanWindow && $alreadyHandled) {
                    continue;
                }

                $caughtUp = false;
                yield $order;
            }

            $pageInfo = $chunk['next_page_info'];

            if ($caughtUp || $pageInfo === null || $chunk['orders'] === []) {
                break;
            }

            $page++;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function matchesImportStatus(array $payload, EcommerceIntegration $integration): bool
    {
        $wanted = ShopifyImportStatus::tryFrom((string) $integration->import_status)
            ?? ShopifyImportStatus::Unfulfilled;

        return $this->mapper->matchesImportStatus($payload, $wanted);
    }
}
