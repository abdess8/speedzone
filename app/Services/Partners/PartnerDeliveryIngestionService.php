<?php

namespace App\Services\Partners;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\City;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Sector;
use App\Models\User;
use App\Services\TrackingNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartnerDeliveryIngestionService
{
    public function __construct(
        private readonly PartnerApiService $partnerApi,
        private readonly PartnerStatusMapper $statusMapper,
        private readonly PartnerDeliveryNormalizer $normalizer,
        private readonly TrackingNumberGenerator $trackingNumbers,
    ) {}

    /**
     * Pull deliveries from the partner API and upsert local orders.
     *
     * @return array{created: int, updated: int, skipped: int, pages: int, errors: array<int, string>}
     *
     * @throws PartnerApiException
     */
    public function sync(Partner $partner, User $actor): array
    {
        if (! $partner->is_active) {
            throw new PartnerApiException("Partner [{$partner->name}] is inactive.");
        }

        $partner->load('fieldMappings');

        if (blank($partner->api_base_url) && blank($partner->endpoint_login)) {
            throw new PartnerApiException('Partner API is not configured.');
        }

        $sellerId = $this->resolveSellerId($partner, $actor);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'pages' => 0,
            'errors' => [],
        ];

        $page = 1;
        $lastPage = 1;

        do {
            $response = $this->partnerApi->getDeliveries($partner, $page);
            $pageData = $this->normalizer->extractPage($response);
            $stats['pages']++;

            foreach ($pageData['items'] as $rawItem) {
                if (! is_array($rawItem)) {
                    $stats['skipped']++;

                    continue;
                }

                try {
                    $result = $this->upsertDelivery($partner, $rawItem, $sellerId);

                    if ($result === 'created') {
                        $stats['created']++;
                    } elseif ($result === 'updated') {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $stats['skipped']++;
                    $code = is_array($rawItem) ? ($rawItem['code'] ?? $rawItem['id'] ?? '?') : '?';
                    $stats['errors'][] = "Delivery [{$code}]: {$e->getMessage()}";
                    Log::warning('Partner delivery ingestion skipped.', [
                        'partner_id' => $partner->id,
                        'delivery' => $rawItem,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            $lastPage = max(1, $pageData['last_page']);
            $page++;
        } while ($page <= $lastPage);

        $partner->forceFill(['last_synced_at' => now()])->save();

        return $stats;
    }

    /**
     * Pull a single delivery from the partner API and upsert the given order.
     *
     * @return array{created: int, updated: int, skipped: int, pages: int, errors: array<int, string>}
     *
     * @throws PartnerApiException
     */
    public function syncOrder(Order $order, User $actor): array
    {
        $order->load('partner.fieldMappings');

        if (! $order->partner_id || ! $order->partner) {
            throw new PartnerApiException(__('partners.sync.order_not_partner'));
        }

        $partner = $order->partner;

        if (! $partner->is_active) {
            throw new PartnerApiException("Partner [{$partner->name}] is inactive.");
        }

        if (blank($partner->api_base_url) && blank($partner->endpoint_login)) {
            throw new PartnerApiException('Partner API is not configured.');
        }

        if (blank($order->external_tracking_code)) {
            throw new PartnerApiException(__('partners.sync.order_no_reference'));
        }

        if (blank($partner->delivery_lookup_param)) {
            throw new PartnerApiException(__('partners.sync.lookup_param_missing'));
        }

        $response = $this->partnerApi->getDeliveryByCode($partner, (string) $order->external_tracking_code);
        $items = $this->normalizer->extractItems($response);

        if ($items === []) {
            throw new PartnerApiException(__('partners.sync.order_not_found'));
        }

        $sellerId = $order->seller_id ?: $this->resolveSellerId($partner, $actor);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'pages' => 1,
            'errors' => [],
        ];

        try {
            $result = $this->upsertDelivery($partner, $items[0], $sellerId, $order);

            if ($result === 'created') {
                $stats['created'] = 1;
            } elseif ($result === 'updated') {
                $stats['updated'] = 1;
            } else {
                $stats['skipped'] = 1;
            }
        } catch (\Throwable $e) {
            Log::warning('Partner single delivery ingestion failed.', [
                'order_id' => $order->id,
                'partner_id' => $partner->id,
                'exception' => $e->getMessage(),
            ]);

            throw new PartnerApiException($e->getMessage());
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $rawItem
     */
    private function upsertDelivery(Partner $partner, array $rawItem, int $sellerId, ?Order $targetOrder = null): string
    {
        $normalized = $this->normalizer->normalize($rawItem, $partner);

        if ($normalized === null) {
            return 'skipped';
        }

        return DB::transaction(function () use ($partner, $normalized, $sellerId, $targetOrder): string {
            if ($targetOrder) {
                $order = Order::query()->whereKey($targetOrder->id)->lockForUpdate()->firstOrFail();
                $isNew = false;
            } else {
                $order = Order::query()
                    ->where('partner_id', $partner->id)
                    ->where('external_tracking_code', $normalized['external_tracking_code'])
                    ->lockForUpdate()
                    ->first();

                $isNew = $order === null;

                if ($isNew) {
                    $order = new Order([
                        'partner_id' => $partner->id,
                        'external_tracking_code' => $normalized['external_tracking_code'],
                        'tracking_number' => $this->trackingNumbers->generate(),
                        'seller_id' => $sellerId,
                    ]);
                }
            }

            $cityId = $this->resolveCityId($partner, $normalized);
            $sectorId = $this->resolveSectorId($partner, $normalized, $cityId);
            $status = $this->resolveStatus($partner, $normalized['status']);

            $previousStatus = $order->exists
                ? ($order->status instanceof OrderStatus ? $order->status->value : (string) $order->status)
                : null;

            $order->fill([
                'customer_first_name' => $normalized['customer_first_name'],
                'customer_last_name' => $normalized['customer_last_name'],
                'customer_phone' => $normalized['customer_phone'],
                'customer_address' => $normalized['customer_address'],
                'city_id' => $cityId,
                'sector_id' => $sectorId,
                'payment_method' => PaymentMethod::CARD_PAYMENT->value,
                'order_amount' => $normalized['order_amount'] ?? 0,
                'delivery_price' => $normalized['delivery_price'] ?? $this->defaultDeliveryPrice($sectorId),
                'notes' => $normalized['notes'],
                'is_fragile' => $normalized['is_fragile'],
                'can_be_opened' => $normalized['can_be_opened'],
                'option_exchange' => $normalized['option_exchange'],
                'status' => $status->value,
            ]);

            if ($targetOrder && blank($order->external_tracking_code)) {
                $order->external_tracking_code = $normalized['external_tracking_code'];
            }

            $order->suppressPartnerStatusSync = true;
            $order->save();

            if ($isNew) {
                $order->recordStatus($status, null, 'Imported from partner API.', isSystem: true);

                return 'created';
            }

            if ($previousStatus !== $status->value) {
                $order->recordStatus($status, null, 'Status updated from partner sync.', isSystem: true);
            }

            return 'updated';
        });
    }

    private function resolveSellerId(Partner $partner, User $actor): int
    {
        $assignedUser = $partner->users()->orderBy('partner_user.created_at')->first();

        if ($assignedUser) {
            return $assignedUser->id;
        }

        if ($actor->managesPartner($partner) || $actor->hasPermission('partners.sync')) {
            return $actor->id;
        }

        throw new PartnerApiException(
            "No user is assigned to partner [{$partner->name}]. Link at least one user before syncing."
        );
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function resolveCityId(Partner $partner, array $normalized): int
    {
        $cityName = $normalized['city_name'] ?? null;

        if ($cityName) {
            $delegated = $partner->cities()
                ->where('name', 'like', $cityName)
                ->first();

            if ($delegated) {
                return $delegated->id;
            }

            $city = City::query()->active()->where('name', 'like', $cityName)->first();

            if ($city) {
                return $city->id;
            }
        }

        $fallback = $partner->cities()->orderBy('name')->first()
            ?? ($partner->reception_city_id ? City::query()->find($partner->reception_city_id) : null);

        if ($fallback) {
            return $fallback->id;
        }

        $anyCity = City::query()->active()->orderBy('name')->value('id');

        if ($anyCity === null) {
            throw new \RuntimeException('No active city found to assign the imported order.');
        }

        return (int) $anyCity;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function resolveSectorId(Partner $partner, array $normalized, int $cityId): ?int
    {
        $sectorName = $normalized['sector_name'] ?? null;

        if ($sectorName) {
            $sector = Sector::query()
                ->active()
                ->forCity($cityId)
                ->where('name', 'like', $sectorName)
                ->first();

            if ($sector) {
                return $sector->id;
            }
        }

        $delegatedSector = $partner->sectors()
            ->where('city_id', $cityId)
            ->orderBy('name')
            ->first();

        return $delegatedSector?->id;
    }

    private function resolveStatus(Partner $partner, ?string $partnerStatus): OrderStatus
    {
        if ($partnerStatus) {
            $mapped = $this->statusMapper->toSpeedzoneStatus($partner, $partnerStatus);

            if ($mapped) {
                return $mapped;
            }
        }

        return OrderStatus::CREATED;
    }

    private function defaultDeliveryPrice(?int $sectorId): float
    {
        if ($sectorId === null) {
            return 0;
        }

        return (float) (Sector::query()->whereKey($sectorId)->value('delivery_price') ?? 0);
    }
}
