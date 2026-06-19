<?php

namespace App\Services\Partners;

use App\Enums\PartnerOrderField;
use App\Models\FieldMapping;
use App\Models\Partner;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Maps heterogeneous partner delivery payloads to a normalized shape.
 * Uses per-partner field mappings when configured, with Sendit-oriented fallbacks.
 */
class PartnerDeliveryNormalizer
{
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    public function normalize(array $item, ?Partner $partner = null): ?array
    {
        $mappings = $this->fieldMappingIndex($partner);

        $externalCode = $this->resolveString($item, PartnerOrderField::EXTERNAL_TRACKING_CODE, $mappings, [
            'code',
            'tracking_number',
            'tracking_code',
            'reference',
            'delivery_code',
            'id',
        ]);

        if ($externalCode === null) {
            return null;
        }

        $fullName = $this->resolveString($item, PartnerOrderField::CUSTOMER_NAME, $mappings, [
            'name',
            'client_name',
            'recipient_name',
            'customer_name',
        ]) ?? 'Partner Client';

        [$firstName, $lastName] = $this->splitName($fullName);

        return [
            'external_tracking_code' => $externalCode,
            'status' => $this->resolveString($item, PartnerOrderField::STATUS, $mappings, [
                'status',
                'delivery_status',
                'state',
                'status_name',
            ]),
            'customer_first_name' => $firstName,
            'customer_last_name' => $lastName,
            'customer_phone' => $this->resolveString($item, PartnerOrderField::CUSTOMER_PHONE, $mappings, [
                'phone',
                'client_phone',
                'recipient_phone',
                'customer_phone',
            ]) ?? '',
            'customer_address' => $this->resolveString($item, PartnerOrderField::CUSTOMER_ADDRESS, $mappings, [
                'address',
                'client_address',
                'recipient_address',
                'customer_address',
            ]) ?? '',
            'city_name' => $this->resolveString($item, PartnerOrderField::CITY_NAME, $mappings, [
                'city',
                'city_name',
                'district',
                'district_name',
                'destination_city',
            ]),
            'sector_name' => $this->resolveString($item, PartnerOrderField::SECTOR_NAME, $mappings, [
                'sector',
                'sector_name',
                'zone',
            ]),
            'order_amount' => $this->resolveNumeric($item, PartnerOrderField::ORDER_AMOUNT, $mappings, [
                'amount',
                'cod_amount',
                'price',
                'total',
                'order_amount',
            ]),
            'delivery_price' => $this->resolveNumeric($item, PartnerOrderField::DELIVERY_PRICE, $mappings, [
                'delivery_price',
                'shipping_price',
                'delivery_fee',
            ]),
            'notes' => $this->resolveString($item, PartnerOrderField::NOTES, $mappings, ['comment', 'notes', 'note']),
            'is_fragile' => $this->resolveBoolean($item, PartnerOrderField::IS_FRAGILE, $mappings, ['is_fragile', 'fragile']),
            'can_be_opened' => $this->resolveBoolean($item, PartnerOrderField::CAN_BE_OPENED, $mappings, ['allow_open', 'can_be_opened', 'openable']),
            'option_exchange' => $this->resolveBoolean($item, PartnerOrderField::OPTION_EXCHANGE, $mappings, ['option_exchange', 'exchange', 'is_exchange']),
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{items: array<int, array<string, mixed>>, current_page: int, last_page: int}
     */
    public function extractPage(array $response): array
    {
        if (isset($response['data']['data']) && is_array($response['data']['data'])) {
            return [
                'items' => $response['data']['data'],
                'current_page' => (int) ($response['data']['current_page'] ?? 1),
                'last_page' => (int) ($response['data']['last_page'] ?? 1),
            ];
        }

        if (isset($response['data']) && is_array($response['data']) && array_is_list($response['data'])) {
            return [
                'items' => $response['data'],
                'current_page' => (int) ($response['meta']['current_page'] ?? $response['current_page'] ?? 1),
                'last_page' => (int) ($response['meta']['last_page'] ?? $response['last_page'] ?? 1),
            ];
        }

        foreach (['deliveries', 'items', 'results'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return [
                    'items' => $response[$key],
                    'current_page' => (int) ($response['current_page'] ?? 1),
                    'last_page' => (int) ($response['last_page'] ?? 1),
                ];
            }
        }

        return ['items' => [], 'current_page' => 1, 'last_page' => 1];
    }

    /**
     * Extract delivery records from paginated or single-delivery API responses.
     *
     * @param  array<string, mixed>  $response
     * @return array<int, array<string, mixed>>
     */
    public function extractItems(array $response): array
    {
        $page = $this->extractPage($response);

        if ($page['items'] !== []) {
            return array_values(array_filter($page['items'], 'is_array'));
        }

        if (isset($response['data']) && is_array($response['data'])) {
            if (array_is_list($response['data'])) {
                return array_values(array_filter($response['data'], 'is_array'));
            }

            if (! isset($response['data']['data'], $response['data']['current_page'])) {
                return [$response['data']];
            }
        }

        if ($this->looksLikeDeliveryRecord($response)) {
            return [$response];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function looksLikeDeliveryRecord(array $record): bool
    {
        foreach (['code', 'status', 'name', 'phone', 'amount'] as $key) {
            if (array_key_exists($key, $record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<string, string>
     */
    private function fieldMappingIndex(?Partner $partner): Collection
    {
        if ($partner === null) {
            return collect();
        }

        if (! $partner->relationLoaded('fieldMappings')) {
            $partner->load('fieldMappings');
        }

        return $partner->fieldMappings->mapWithKeys(function (FieldMapping $mapping) {
            $field = $mapping->speedzone_field instanceof PartnerOrderField
                ? $mapping->speedzone_field->value
                : (string) $mapping->speedzone_field;

            return [$field => $mapping->partner_field];
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, string>  $mappings
     * @param  array<int, string>  $fallbackPaths
     */
    private function resolveString(
        array $item,
        PartnerOrderField $field,
        Collection $mappings,
        array $fallbackPaths,
    ): ?string {
        $paths = $this->pathsFor($field, $mappings, $fallbackPaths);

        foreach ($paths as $path) {
            $value = Arr::get($item, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, string>  $mappings
     * @param  array<int, string>  $fallbackPaths
     */
    private function resolveNumeric(
        array $item,
        PartnerOrderField $field,
        Collection $mappings,
        array $fallbackPaths,
    ): ?float {
        $paths = $this->pathsFor($field, $mappings, $fallbackPaths);

        foreach ($paths as $path) {
            $value = Arr::get($item, $path);

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, string>  $mappings
     * @param  array<int, string>  $fallbackPaths
     */
    private function resolveBoolean(
        array $item,
        PartnerOrderField $field,
        Collection $mappings,
        array $fallbackPaths,
    ): bool {
        $paths = $this->pathsFor($field, $mappings, $fallbackPaths);

        foreach ($paths as $path) {
            $value = Arr::get($item, $path);

            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (int) $value !== 0;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));

                if (in_array($normalized, ['1', 'true', 'yes', 'oui'], true)) {
                    return true;
                }

                if (in_array($normalized, ['0', 'false', 'no', 'non', ''], true)) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * @param  Collection<string, string>  $mappings
     * @param  array<int, string>  $fallbackPaths
     * @return array<int, string>
     */
    private function pathsFor(PartnerOrderField $field, Collection $mappings, array $fallbackPaths): array
    {
        $mapped = $mappings->get($field->value);

        if ($mapped) {
            return [$mapped, ...$fallbackPaths];
        }

        return $fallbackPaths;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);

        if ($fullName === '') {
            return ['Partner', 'Client'];
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [];

        return [
            $parts[0] ?? 'Partner',
            $parts[1] ?? '',
        ];
    }
}
