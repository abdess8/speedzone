<?php

namespace App\Services\Ecommerce\YouCan;

use App\Enums\YouCanImportStatus;
use App\Models\EcommerceIntegration;
use Illuminate\Support\Carbon;

class YouCanOrderSyncDriver
{
    public function __construct(
        private readonly YouCanClient $client,
        private readonly YouCanOrderMapper $mapper,
    ) {}

    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function fetchOrders(EcommerceIntegration $integration, ?Carbon $since): iterable
    {
        $password = (string) $integration->client_secret;
        $storeId = (string) $integration->external_store_id;
        $email = (string) $integration->email;

        if ($password === '' || $storeId === '' || $email === '') {
            throw new \RuntimeException(__('integrations.youcan.errors.credentials'));
        }

        $this->client->authenticateForStore($email, $password, $storeId);

        $page = 1;
        $maxPages = max(1, (int) config('youcan.orders_max_pages', 10));
        $perPage = max(1, (int) config('youcan.orders_page_size', 50));
        $handled = $this->mapper->handledCatalogKeys($integration);

        while ($page <= $maxPages) {
            $chunk = $this->client->orders($page, $perPage);
            $caughtUp = $chunk['orders'] !== [];

            foreach ($chunk['orders'] as $order) {
                $order = $this->hydrate($order);
                $externalId = $this->mapper->externalId($order);
                $createdAt = $this->mapper->createdAt($order);
                $olderThanWindow = $since !== null && $createdAt !== null && $createdAt->lt($since);
                $alreadyHandled = $this->mapper->isHandled(
                    $handled,
                    $externalId,
                    $this->mapper->orderRef($order),
                );

                // Incremental runs skip parcels already imported *and* rows
                // that already failed or sit in the review table. Those stay
                // on the last sync so they are not pulled again.
                if ($olderThanWindow && $alreadyHandled) {
                    continue;
                }

                $caughtUp = false;
                yield $order;
            }

            if ($caughtUp || ! ($chunk['has_more'] ?? false) || $chunk['orders'] === []) {
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
        $wanted = YouCanImportStatus::tryFrom((string) $integration->import_status)
            ?? YouCanImportStatus::Open;

        return $this->mapper->matchesImportStatus($payload, $wanted);
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function hydrate(array $order): array
    {
        $fields = $order['extra_fields'] ?? $order['extraFields'] ?? null;

        if (is_array($fields) && $fields !== []) {
            return $order;
        }

        $id = $order['id'] ?? $order['uuid'] ?? null;

        if (! is_string($id) || $id === '') {
            return $order;
        }

        $detail = $this->client->order($id);

        return $detail !== [] ? array_replace($order, $detail) : $order;
    }
}
