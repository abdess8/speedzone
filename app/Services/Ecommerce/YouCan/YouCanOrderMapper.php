<?php

namespace App\Services\Ecommerce\YouCan;

use App\Enums\OrderCreationSource;
use App\Enums\PaymentMethod;
use App\Enums\YouCanImportStatus;
use App\Models\City;
use App\Models\EcommerceIntegration;
use App\Models\EcommerceIntegrationSync;
use App\Models\EcommerceIntegrationSyncRow;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class YouCanOrderMapper
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function matchesImportStatus(array $payload, YouCanImportStatus $wanted): bool
    {
        $slug = $wanted->value;

        if ($wanted->isPaymentStatus()) {
            return $this->paymentStatusSlug($payload) === $slug;
        }

        return $this->orderStatusSlug($payload) === $slug;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createdAt(array $payload): ?Carbon
    {
        $raw = $payload['created_at'] ?? $payload['createdAt'] ?? null;

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
        $id = $payload['id'] ?? $payload['uuid'] ?? null;

        return filled($id) ? (string) $id : null;
    }

    /**
     * Shop-facing order number (YouCan `ref`, later Shopify `#1001`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function orderRef(array $payload): ?string
    {
        foreach (['ref', 'reference', 'order_number', 'number'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr(trim((string) $value), 0, 100);
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
        $externalId = filled($externalId) ? (string) $externalId : null;
        $orderRef = filled($orderRef) ? (string) $orderRef : null;

        if ($externalId === null && $orderRef === null) {
            return false;
        }

        return Order::acrossStores()
            ->where(function ($query) use ($integration, $externalId, $orderRef) {
                if ($externalId !== null) {
                    $query->where(function ($inner) use ($integration, $externalId) {
                        $inner->where('ecommerce_integration_id', $integration->id)
                            ->where('external_order_id', $externalId);
                    });
                }

                if ($orderRef !== null) {
                    $method = $externalId !== null ? 'orWhere' : 'where';
                    $query->{$method}(function ($inner) use ($integration, $orderRef) {
                        $inner->where('store_id', $integration->store_id)
                            ->where('ecommerce_order_ref', $orderRef);
                    });
                }
            })
            ->exists();
    }

    /**
     * Orders this connector already knows: imported parcels, plus staged
     * review/failed rows that must not be pulled again.
     *
     * @return array{ids: array<string, true>, refs: array<string, true>}
     */
    public function handledCatalogKeys(EcommerceIntegration $integration): array
    {
        $ids = [];
        $refs = [];

        Order::acrossStores()
            ->where(function ($query) use ($integration) {
                $query->where('ecommerce_integration_id', $integration->id)
                    ->orWhere(function ($inner) use ($integration) {
                        $inner->where('store_id', $integration->store_id)
                            ->whereNotNull('ecommerce_order_ref');
                    });
            })
            ->select(['id', 'external_order_id', 'ecommerce_order_ref', 'ecommerce_integration_id', 'store_id'])
            ->chunkById(500, function ($orders) use ($integration, &$ids, &$refs) {
                foreach ($orders as $order) {
                    if ((int) $order->ecommerce_integration_id === (int) $integration->id && filled($order->external_order_id)) {
                        $ids[(string) $order->external_order_id] = true;
                    }

                    if ((int) $order->store_id === (int) $integration->store_id && filled($order->ecommerce_order_ref)) {
                        $refs[(string) $order->ecommerce_order_ref] = true;
                    }
                }
            });

        EcommerceIntegrationSyncRow::query()
            ->where('ecommerce_integration_id', $integration->id)
            ->select(['id', 'external_order_id', 'ref'])
            ->chunkById(500, function ($rows) use (&$ids, &$refs) {
                foreach ($rows as $row) {
                    if (filled($row->external_order_id)) {
                        $ids[(string) $row->external_order_id] = true;
                    }

                    if (filled($row->ref)) {
                        $refs[(string) $row->ref] = true;
                    }
                }
            });

        return ['ids' => $ids, 'refs' => $refs];
    }

    /**
     * @param  array{ids: array<string, true>, refs: array<string, true>}  $handled
     */
    public function isHandled(array $handled, ?string $externalId, ?string $orderRef): bool
    {
        if (filled($externalId) && isset($handled['ids'][(string) $externalId])) {
            return true;
        }

        return filled($orderRef) && isset($handled['refs'][(string) $orderRef]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{first_name: string, last_name: string, phone: string, address: ?string, city: ?string}
     */
    private function customer(array $payload): array
    {
        $raw = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $fullName = $this->extra($payload, [
            'nom complet', 'full name', 'fullname', 'name', 'nom', 'اسم الكامل',
        ]) ?? trim((string) ($raw['full_name'] ?? $raw['name'] ?? ''));

        $first = trim((string) ($raw['first_name'] ?? $raw['firstName'] ?? ''));
        $last = trim((string) ($raw['last_name'] ?? $raw['lastName'] ?? ''));

        if ($first === '' && $fullName !== '') {
            [$first, $last] = $this->splitName($fullName);
        }

        $shipping = is_array($payload['shipping'] ?? null) ? $payload['shipping'] : [];
        $address = $this->extra($payload, ['adresse', 'address', 'adresse de livraison'])
            ?? $this->stringValue($shipping['address'] ?? null)
            ?? $this->stringValue($raw['address'] ?? $raw['location'] ?? null);
        $city = $this->extra($payload, ['ville', 'city', 'ville de livraison'])
            ?? $this->stringValue($shipping['city'] ?? null)
            ?? $this->stringValue($raw['city'] ?? null);

        return [
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $this->firstPhone([
                $this->extra($payload, ['téléphone', 'telephone', 'phone', 'tel', 'رقم الهاتف', 'gsm', 'whatsapp', 'portable', 'mobile']),
                $raw['phone'] ?? null,
                $raw['mobile'] ?? null,
                $shipping['phone'] ?? null,
            ]),
            'address' => $address,
            'city' => $city,
        ];
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

        if (is_array($value)) {
            return $this->stringValue(
                $value['address']
                    ?? $value['line1']
                    ?? $value['street']
                    ?? $value['city']
                    ?? null
            );
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $full): array
    {
        $full = trim(preg_replace('/\s+/', ' ', $full) ?? $full);
        $parts = explode(' ', $full, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    public function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '00212')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '2120') && strlen($digits) === 13) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '212') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 3);
        }

        if (strlen($digits) === 9 && ($digits[0] === '6' || $digits[0] === '7')) {
            $digits = '0'.$digits;
        }

        return preg_match('/^0[0-9]{9}$/', $digits) === 1 ? $digits : null;
    }

    private ?Collection $cities = null;

    private function matchCity(?string $name): ?City
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $needle = Str::lower($this->fold($name));

        $exact = $this->cities()->first(function (City $city) use ($needle): bool {
            return Str::lower($this->fold($city->name)) === $needle;
        });

        if ($exact instanceof City) {
            return $exact;
        }

        return $this->cities()->first(function (City $city) use ($needle): bool {
            $hay = Str::lower($this->fold($city->name));

            return str_contains($hay, $needle) || str_contains($needle, $hay);
        });
    }

    /**
     * @return Collection<int, City>
     */
    private function cities(): Collection
    {
        return $this->cities ??= City::query()->active()->get(['id', 'name']);
    }

    private function fold(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($ascii) && trim($ascii) !== '' ? $ascii : $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function amount(array $payload): float
    {
        $raw = $payload['total']
            ?? $payload['total_price']
            ?? data_get($payload, 'payment.total')
            ?? 0;

        return round((float) $raw, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paymentMethod(array $payload): PaymentMethod
    {
        $slug = $this->paymentStatusSlug($payload);

        if (in_array($slug, ['paid', 'captured'], true)) {
            return PaymentMethod::CARD_PAYMENT;
        }

        return PaymentMethod::CASH;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function orderStatusSlug(array $payload): ?string
    {
        $slug = data_get($payload, 'status_new.slug')
            ?? data_get($payload, 'status.slug')
            ?? (is_string($payload['status'] ?? null) ? $payload['status'] : null);

        return is_string($slug) && $slug !== '' ? Str::lower($slug) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function paymentStatusSlug(array $payload): ?string
    {
        $slug = data_get($payload, 'paymentStatus.slug')
            ?? data_get($payload, 'payment.status.slug')
            ?? data_get($payload, 'payment_status.slug')
            ?? data_get($payload, 'payment.status')
            ?? (is_string($payload['paymentStatus'] ?? null) ? $payload['paymentStatus'] : null)
            ?? (is_string($payload['payment_status'] ?? null) ? $payload['payment_status'] : null);

        return is_string($slug) && $slug !== '' ? Str::lower($slug) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name: string, slug: string, value: string}>
     */
    public function publicExtraFields(array $payload): array
    {
        return $this->extraFields($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $mapping
     */
    public function sourceValue(array $payload, ?string $sourceKey): ?string
    {
        if (! is_string($sourceKey) || $sourceKey === '') {
            return null;
        }

        if (str_starts_with($sourceKey, 'extra:')) {
            $wanted = substr($sourceKey, 6);

            foreach ($this->extraFields($payload) as $field) {
                if ($field['name'] === $wanted) {
                    return $field['value'];
                }
            }

            return $this->extra($payload, [$wanted]);
        }

        if ($sourceKey === 'customer.full_name') {
            $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
            $full = trim((string) ($customer['full_name'] ?? $customer['name'] ?? ''));

            return $full !== '' ? $full : null;
        }

        if ($sourceKey === 'customer.phone') {
            $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];

            return filled($customer['phone'] ?? null) ? (string) $customer['phone'] : null;
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

        foreach (YouCanFieldCatalog::TARGETS as $target) {
            $key = $target['key'];
            $mapped = $this->sourceValue($payload, $mapping[$key] ?? null);

            $raw[$key] = $mapped ?? match ($key) {
                'customer_first_name' => $fallback['first_name'] ?? '',
                'customer_last_name' => '',
                'customer_phone' => $fallback['phone'] ?? '',
                'city_id' => $fallback['city'] ?? '',
                'customer_address' => $fallback['address'] ?? '',
                'order_amount' => (string) $this->amount($payload),
                'notes' => is_string($payload['notes'] ?? null) ? (string) $payload['notes'] : '',
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
            [$first, $last] = $this->splitName($raw['customer_first_name']);
            $raw['customer_first_name'] = $first;
            $raw['customer_last_name'] = $last;
        }

        return array_map(static fn ($value) => is_string($value) ? $value : '', $raw);
    }

    /**
     * Best-effort SpeedZone values for the review table (ids may be null).
     *
     * @param  array<string, string>  $raw
     * @return array<string, mixed>
     */
    public function resolveValues(array $raw): array
    {
        $phone = $this->normalizePhone($raw['customer_phone'] ?? '');
        $city = $this->matchCity($raw['city_id'] ?? null);
        $sector = $city?->activeSectors()->orderBy('id')->first();
        $payment = $this->parsePayment($raw['payment_method'] ?? '');
        $amount = is_numeric($raw['order_amount'] ?? null) ? round((float) $raw['order_amount'], 2) : $raw['order_amount'];

        $last = $raw['customer_last_name'] ?? '';
        $first = $raw['customer_first_name'] ?? '';

        if (trim($last) === '' && trim($first) !== '') {
            $last = $first;
        }

        return [
            'customer_first_name' => $first,
            'customer_last_name' => $last,
            'customer_phone' => $phone ?? ($raw['customer_phone'] ?? ''),
            'customer_address' => $raw['customer_address'] ?? '',
            'city_id' => $city?->id,
            'sector_id' => $sector?->id,
            'payment_method' => $payment,
            'order_amount' => $amount === null || $amount === '' ? '' : (string) $amount,
            'notes' => $raw['notes'] ?? '',
            'is_fragile' => $this->parseFlag($raw['is_fragile'] ?? ''),
            'can_be_opened' => $this->parseFlag($raw['can_be_opened'] ?? ''),
            'option_exchange' => $this->parseFlag($raw['option_exchange'] ?? ''),
            'delivery_included' => $this->parseFlag($raw['delivery_included'] ?? ''),
        ];
    }

    private function parsePayment(string $raw): string
    {
        $slug = Str::lower($this->fold($raw));

        if (in_array($slug, ['paid', 'captured', 'card', 'card_payment', 'carte'], true)) {
            return PaymentMethod::CARD_PAYMENT->value;
        }

        return PaymentMethod::CASH->value;
    }

    private function parseFlag(string $raw): bool
    {
        $token = Str::lower(trim($raw));

        return in_array($token, ['1', 'true', 'oui', 'yes', 'vrai'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name: string, slug: string, value: string}>
     */
    private function extraFields(array $payload): array
    {
        $fields = $payload['extra_fields'] ?? $payload['extraFields'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        $normalized = [];

        foreach ($fields as $key => $field) {
            if (is_array($field)) {
                $name = $field['name'] ?? $field['label'] ?? $field['slug'] ?? null;
                $value = $field['value'] ?? $field['content'] ?? null;

                if (is_string($name) && (is_string($value) || is_numeric($value)) && trim((string) $value) !== '') {
                    $normalized[] = [
                        'name' => $name,
                        'slug' => (string) ($field['slug'] ?? ''),
                        'value' => trim((string) $value),
                    ];
                }

                continue;
            }

            if (is_string($key) && ! is_numeric($key) && (is_string($field) || is_numeric($field)) && trim((string) $field) !== '') {
                $normalized[] = [
                    'name' => $key,
                    'slug' => '',
                    'value' => trim((string) $field),
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $aliases
     */
    private function extra(array $payload, array $aliases): ?string
    {
        $needles = array_values(array_filter(
            array_map(fn (string $alias) => trim(Str::lower($this->fold($alias))), $aliases),
            fn (string $needle) => $needle !== '' && mb_strlen($needle) >= 3
        ));

        foreach ($this->extraFields($payload) as $field) {
            $label = trim(Str::lower($this->fold($field['name'])));
            $slug = trim(Str::lower($this->fold($field['slug'])));
            $haystacks = array_values(array_filter([$label, $slug, ...preg_split('/\s*\/\s*/', $label) ?: []]));

            foreach ($needles as $needle) {
                foreach ($haystacks as $haystack) {
                    if ($haystack === $needle) {
                        return $field['value'];
                    }

                    if (mb_strlen($needle) >= 4 && str_contains($haystack, $needle)) {
                        return $field['value'];
                    }
                }
            }
        }

        return null;
    }
}
