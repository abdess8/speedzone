<?php

namespace App\Services\Ecommerce\Shopify;

use App\Enums\OrderCreationSource;
use App\Enums\PaymentMethod;
use App\Enums\ShopifyImportStatus;
use App\Models\EcommerceIntegration;
use App\Models\EcommerceIntegrationSync;
use App\Services\Ecommerce\YouCan\YouCanOrderMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ShopifyOrderMapper
{
    public function __construct(private readonly YouCanOrderMapper $base) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function matchesImportStatus(array $payload, ShopifyImportStatus $wanted): bool
    {
        $slug = $wanted->value;

        if ($wanted->isFinancialStatus()) {
            return $this->paymentStatusSlug($payload) === $slug;
        }

        if ($wanted->isFulfillmentStatus()) {
            return $this->fulfillmentStatusSlug($payload) === $slug;
        }

        return $this->orderStatusSlug($payload) === $slug;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createdAt(array $payload): ?Carbon
    {
        $raw = $payload['updated_at'] ?? $payload['created_at'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function externalId(array $payload): ?string
    {
        $id = $payload['id'] ?? null;

        return filled($id) ? (string) $id : null;
    }

    /**
     * Shop-facing number (`#1001`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function orderRef(array $payload): ?string
    {
        foreach (['name', 'order_number', 'number'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                $ref = trim((string) $value);

                if ($key === 'order_number' && ! str_starts_with($ref, '#')) {
                    $ref = '#'.$ref;
                }

                return mb_substr($ref, 0, 100);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, reason: string, detail: ?string}
     */
    public function toOrderPayload(
        array $payload,
        EcommerceIntegration $integration,
        EcommerceIntegrationSync $sync,
    ): array {
        $externalId = $this->externalId($payload);

        if ($externalId === null) {
            return ['ok' => false, 'reason' => 'missing_id', 'detail' => null];
        }

        if ($this->alreadyImported($integration, $externalId, $this->orderRef($payload))) {
            return ['ok' => false, 'reason' => 'already_imported', 'detail' => $externalId];
        }

        $raw = $this->extractRaw($payload, $integration->resolvedFieldMapping());
        $values = $this->resolveValues($raw);
        $phone = $this->normalizePhone((string) $values['customer_phone']);

        if ($phone === null) {
            return ['ok' => false, 'reason' => 'invalid_phone', 'detail' => (string) ($raw['customer_phone'] ?? '')];
        }

        if (blank($values['customer_first_name']) && blank($values['customer_last_name'])) {
            return ['ok' => false, 'reason' => 'missing_customer', 'detail' => null];
        }

        if (blank($values['customer_address'])) {
            return ['ok' => false, 'reason' => 'missing_address', 'detail' => $raw['customer_address'] ?? null];
        }

        if ($values['city_id'] === null) {
            return ['ok' => false, 'reason' => 'unknown_city', 'detail' => $raw['city_id'] ?? null];
        }

        if ($values['sector_id'] === null) {
            return ['ok' => false, 'reason' => 'no_sector', 'detail' => $raw['city_id'] ?? null];
        }

        $amount = is_numeric($values['order_amount']) ? round((float) $values['order_amount'], 2) : $this->amount($payload);
        $payment = PaymentMethod::tryFrom((string) $values['payment_method']) ?? PaymentMethod::CASH;

        return [
            'ok' => true,
            'data' => [
                'store_id' => $integration->store_id,
                'creation_source' => OrderCreationSource::Integration->value,
                'ecommerce_integration_id' => $integration->id,
                'external_order_id' => $externalId,
                'ecommerce_order_ref' => $this->orderRef($payload),
                'ecommerce_sync_id' => $sync->id,
                'customer_first_name' => $values['customer_first_name'] ?: $values['customer_last_name'],
                'customer_last_name' => $values['customer_first_name'] ? $values['customer_last_name'] : '',
                'customer_phone' => $phone,
                'customer_address' => $values['customer_address'],
                'city_id' => $values['city_id'],
                'sector_id' => $values['sector_id'],
                'payment_method' => $payment->value,
                'order_amount' => $payment === PaymentMethod::CASH ? $amount : null,
                'order_value' => $amount,
                'notes' => filled($values['notes']) ? $values['notes'] : null,
                'is_fragile' => (bool) $values['is_fragile'],
                'can_be_opened' => (bool) $values['can_be_opened'],
                'option_exchange' => (bool) $values['option_exchange'],
                'delivery_included' => (bool) $values['delivery_included'],
            ],
        ];
    }

    public function alreadyImported(
        EcommerceIntegration $integration,
        ?string $externalId = null,
        ?string $orderRef = null,
    ): bool {
        return $this->base->alreadyImported($integration, $externalId, $orderRef);
    }

    /**
     * @return array{ids: array<string, true>, refs: array<string, true>}
     */
    public function handledCatalogKeys(EcommerceIntegration $integration): array
    {
        return $this->base->handledCatalogKeys($integration);
    }

    /**
     * @param  array{ids: array<string, true>, refs: array<string, true>}  $handled
     */
    public function isHandled(array $handled, ?string $externalId, ?string $orderRef): bool
    {
        return $this->base->isHandled($handled, $externalId, $orderRef);
    }

    public function normalizePhone(string $raw): ?string
    {
        return $this->base->normalizePhone($raw);
    }

    /**
     * @param  array<string, string>  $raw
     * @return array<string, mixed>
     */
    public function resolveValues(array $raw): array
    {
        return $this->base->resolveValues($raw);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function orderStatusSlug(array $payload): ?string
    {
        if (filled($payload['cancelled_at'] ?? null) || filled($payload['cancel_reason'] ?? null)) {
            return 'cancelled';
        }

        $status = $payload['status'] ?? null;

        if (is_string($status) && $status !== '') {
            return Str::lower($status);
        }

        if (filled($payload['closed_at'] ?? null)) {
            return 'closed';
        }

        return 'open';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fulfillmentStatusSlug(array $payload): ?string
    {
        $raw = $payload['fulfillment_status'] ?? null;

        if ($raw === null || $raw === '') {
            return ShopifyImportStatus::Unfulfilled->value;
        }

        return is_string($raw) ? Str::lower($raw) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function paymentStatusSlug(array $payload): ?string
    {
        $slug = $payload['financial_status'] ?? null;

        return is_string($slug) && $slug !== '' ? Str::lower($slug) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name: string, slug: string, value: string}>
     */
    public function publicNoteAttributes(array $payload): array
    {
        return $this->noteAttributes($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sourceValue(array $payload, ?string $sourceKey): ?string
    {
        if (! is_string($sourceKey) || $sourceKey === '') {
            return null;
        }

        if (str_starts_with($sourceKey, 'note:')) {
            $wanted = substr($sourceKey, 5);

            foreach ($this->noteAttributes($payload) as $field) {
                if ($field['name'] === $wanted) {
                    return $field['value'];
                }
            }

            return $this->note($payload, [$wanted]);
        }

        $value = data_get($payload, $sourceKey);

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value) || (is_string($value) && trim($value) !== '')) {
            return trim((string) $value);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $mapping
     * @return array<string, string>
     */
    public function extractRaw(array $payload, array $mapping): array
    {
        $fallback = $this->customer($payload);
        $raw = [];

        foreach (ShopifyFieldCatalog::TARGETS as $target) {
            $key = $target['key'];
            $mapped = $this->sourceValue($payload, $mapping[$key] ?? null);

            $raw[$key] = $mapped ?? match ($key) {
                'customer_first_name' => $fallback['first_name'] ?? '',
                'customer_last_name' => '',
                'customer_phone' => $fallback['phone'] ?? '',
                'city_id' => $fallback['city'] ?? '',
                'customer_address' => $fallback['address'] ?? '',
                'order_amount' => (string) $this->amount($payload),
                'notes' => is_string($payload['note'] ?? null) ? (string) $payload['note'] : '',
                'payment_method' => $this->paymentStatusSlug($payload) ?? '',
                default => '',
            };

            if ($key === 'customer_last_name' && $raw[$key] === '' && $mapped === null) {
                $raw[$key] = $fallback['last_name'] ?? '';
            }
        }

        $firstMapped = $this->sourceValue($payload, $mapping['customer_first_name'] ?? null);
        $lastMapped = $this->sourceValue($payload, $mapping['customer_last_name'] ?? null);

        if ($lastMapped === null && ($raw['customer_last_name'] ?? '') !== '' && $firstMapped !== null && str_contains($firstMapped, ' ')) {
            $raw['customer_last_name'] = '';
        }

        if (($raw['customer_last_name'] ?? '') === '' && ($raw['customer_first_name'] ?? '') !== '') {
            $parts = explode(' ', trim(preg_replace('/\s+/', ' ', $raw['customer_first_name']) ?? $raw['customer_first_name']), 2);
            $raw['customer_first_name'] = $parts[0];
            $raw['customer_last_name'] = $parts[1] ?? '';
        }

        return array_map(static fn ($value) => is_string($value) ? $value : '', $raw);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{first_name: string, last_name: string, phone: string, address: ?string, city: ?string}
     */
    private function customer(array $payload): array
    {
        $shipping = is_array($payload['shipping_address'] ?? null) ? $payload['shipping_address'] : [];
        $billing = is_array($payload['billing_address'] ?? null) ? $payload['billing_address'] : [];
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];

        $first = trim((string) ($shipping['first_name'] ?? $customer['first_name'] ?? $billing['first_name'] ?? ''));
        $last = trim((string) ($shipping['last_name'] ?? $customer['last_name'] ?? $billing['last_name'] ?? ''));

        if ($first === '') {
            $full = trim((string) ($shipping['name'] ?? $customer['first_name'] ?? ''));

            if ($full !== '') {
                $parts = explode(' ', preg_replace('/\s+/', ' ', $full) ?? $full, 2);
                $first = $parts[0];
                $last = $last !== '' ? $last : ($parts[1] ?? '');
            }
        }

        return [
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $this->firstPhone([
                $this->note($payload, ['téléphone', 'telephone', 'phone', 'tel', 'gsm', 'whatsapp', 'portable', 'mobile']),
                $shipping['phone'] ?? null,
                $customer['phone'] ?? null,
                $billing['phone'] ?? null,
            ]),
            'address' => $this->joinAddress($shipping) ?? $this->joinAddress($billing),
            'city' => $this->stringValue($shipping['city'] ?? $billing['city'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function joinAddress(array $address): ?string
    {
        $line1 = trim((string) ($address['address1'] ?? ''));
        $line2 = trim((string) ($address['address2'] ?? ''));
        $joined = trim($line1.($line2 !== '' ? ' '.$line2 : ''));

        return $joined !== '' ? $joined : null;
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstPhone(array $candidates): string
    {
        $fallback = '';

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                $candidate = (string) $candidate;
            }

            if (! is_string($candidate)) {
                continue;
            }

            $raw = trim($candidate);

            if ($raw === '') {
                continue;
            }

            if ($fallback === '') {
                $fallback = $raw;
            }

            if ($this->normalizePhone($raw) !== null) {
                return $raw;
            }
        }

        return $fallback;
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function amount(array $payload): float
    {
        $raw = $payload['current_total_price']
            ?? $payload['total_price']
            ?? $payload['total_outstanding']
            ?? 0;

        return round((float) $raw, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name: string, slug: string, value: string}>
     */
    private function noteAttributes(array $payload): array
    {
        $fields = $payload['note_attributes'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        $normalized = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = $field['name'] ?? $field['key'] ?? null;
            $value = $field['value'] ?? null;

            if (is_string($name) && (is_string($value) || is_numeric($value)) && trim((string) $value) !== '') {
                $normalized[] = [
                    'name' => $name,
                    'slug' => '',
                    'value' => trim((string) $value),
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $aliases
     */
    private function note(array $payload, array $aliases): ?string
    {
        $needles = array_values(array_filter(
            array_map(fn (string $alias) => trim(Str::lower($this->fold($alias))), $aliases),
            fn (string $needle) => $needle !== '' && mb_strlen($needle) >= 3
        ));

        foreach ($this->noteAttributes($payload) as $field) {
            $label = trim(Str::lower($this->fold($field['name'])));

            foreach ($needles as $needle) {
                if ($label === $needle || (mb_strlen($needle) >= 4 && str_contains($label, $needle))) {
                    return $field['value'];
                }
            }
        }

        return null;
    }

    private function fold(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($ascii) && trim($ascii) !== '' ? $ascii : $value;
    }
}
