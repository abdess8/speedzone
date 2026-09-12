<?php

namespace App\Services\Ecommerce\YouCan;

/**
 * YouCan source columns a seller can map onto a SpeedZone order field.
 *
 * Native customer attributes are always offered. Checkout extra fields are
 * discovered from the last fetched orders and stored on the integration.
 */
class YouCanFieldCatalog
{
    /**
     * SpeedZone order fields, in the same order as the bulk import table.
     *
     * @var array<int, array{key: string, required: bool, aliases: array<int, string>}>
     */
    public const TARGETS = [
        ['key' => 'customer_first_name', 'required' => true, 'aliases' => ['prenom', 'first name', 'firstname', 'nom complet', 'full name', 'fullname', 'name', 'nom']],
        ['key' => 'customer_last_name', 'required' => true, 'aliases' => ['nom', 'nom de famille', 'last name', 'lastname', 'surname']],
        ['key' => 'customer_phone', 'required' => true, 'aliases' => ['telephone', 'tel', 'gsm', 'phone', 'mobile', 'whatsapp', 'portable']],
        ['key' => 'city_id', 'required' => true, 'aliases' => ['ville', 'city', 'ville de livraison']],
        ['key' => 'sector_id', 'required' => false, 'aliases' => ['secteur', 'sector', 'zone', 'quartier']],
        ['key' => 'customer_address', 'required' => true, 'aliases' => ['adresse', 'address', 'adresse de livraison', 'shipping address']],
        ['key' => 'payment_method', 'required' => false, 'aliases' => ['paiement', 'payment', 'payment method', 'paymentstatus']],
        ['key' => 'order_amount', 'required' => true, 'aliases' => ['montant', 'total', 'amount', 'prix', 'crbt', 'cod']],
        ['key' => 'notes', 'required' => false, 'aliases' => ['notes', 'note', 'remarque', 'commentaire']],
        ['key' => 'is_fragile', 'required' => false, 'aliases' => ['fragile']],
        ['key' => 'can_be_opened', 'required' => false, 'aliases' => ['ouverture', 'ouvrir']],
        ['key' => 'option_exchange', 'required' => false, 'aliases' => ['echange', 'exchange']],
        ['key' => 'delivery_included', 'required' => false, 'aliases' => ['livraison incluse', 'delivery included']],
    ];

    /**
     * @return array<int, array{key: string, label: string, group: string}>
     */
    public static function builtinSources(): array
    {
        return [
            ['key' => 'customer.first_name', 'label' => 'YouCan · prénom', 'group' => 'customer'],
            ['key' => 'customer.last_name', 'label' => 'YouCan · nom', 'group' => 'customer'],
            ['key' => 'customer.full_name', 'label' => 'YouCan · nom complet', 'group' => 'customer'],
            ['key' => 'customer.phone', 'label' => 'YouCan · téléphone', 'group' => 'customer'],
            ['key' => 'customer.city', 'label' => 'YouCan · ville', 'group' => 'customer'],
            ['key' => 'customer.address', 'label' => 'YouCan · adresse', 'group' => 'customer'],
            ['key' => 'total', 'label' => 'YouCan · total', 'group' => 'order'],
            ['key' => 'notes', 'label' => 'YouCan · notes', 'group' => 'order'],
            ['key' => 'ref', 'label' => 'YouCan · référence', 'group' => 'order'],
            ['key' => 'paymentStatus.slug', 'label' => 'YouCan · statut de paiement', 'group' => 'order'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{key: string, label: string, group: string, sample?: string}>
     */
    public static function extrasFrom(array $payload): array
    {
        $extras = [];

        foreach ((new YouCanOrderMapper)->publicExtraFields($payload) as $field) {
            $name = $field['name'];

            if ($name === '') {
                continue;
            }

            $extras[] = [
                'key' => 'extra:'.$name,
                'label' => $name,
                'group' => 'extra',
                'sample' => $field['value'],
            ];
        }

        return $extras;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $payloads
     * @return array<int, array{key: string, label: string, group: string, sample?: string}>
     */
    public static function discover(iterable $payloads): array
    {
        $byKey = [];

        foreach (self::builtinSources() as $source) {
            $byKey[$source['key']] = $source;
        }

        foreach ($payloads as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            foreach (self::extrasFrom($payload) as $source) {
                $existing = $byKey[$source['key']] ?? null;

                if ($existing === null || (($existing['sample'] ?? '') === '' && ($source['sample'] ?? '') !== '')) {
                    $byKey[$source['key']] = $source;
                }
            }
        }

        return array_values($byKey);
    }

    /**
     * @param  array<int, array{key: string, label?: string}>  $sources
     * @return array<string, string|null>
     */
    public static function autoMap(array $sources): array
    {
        $pairs = [];

        foreach ($sources as $source) {
            $header = $source['label'] ?? $source['key'];

            foreach (self::TARGETS as $target) {
                $score = self::matchScore($header.' '.$source['key'], [
                    $target['key'],
                    ...$target['aliases'],
                ]);

                if ($score > 0) {
                    $pairs[] = ['source' => $source['key'], 'target' => $target['key'], 'score' => $score];
                }
            }
        }

        usort($pairs, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $taken = [];
        $mapping = [];

        foreach (self::TARGETS as $target) {
            $mapping[$target['key']] = null;
        }

        foreach ($pairs as $pair) {
            if ($mapping[$pair['target']] !== null || isset($taken[$pair['source']])) {
                continue;
            }

            $mapping[$pair['target']] = $pair['source'];
            $taken[$pair['source']] = true;
        }

        if ($mapping['customer_first_name'] === null) {
            $mapping['customer_first_name'] = 'customer.full_name';
        }

        if ($mapping['customer_phone'] === null) {
            $mapping['customer_phone'] = 'customer.phone';
        }

        if ($mapping['city_id'] === null) {
            $mapping['city_id'] = 'customer.city';
        }

        if ($mapping['customer_address'] === null) {
            $mapping['customer_address'] = 'customer.address';
        }

        if ($mapping['order_amount'] === null) {
            $mapping['order_amount'] = 'total';
        }

        return $mapping;
    }

    /**
     * Keep seller choices; fill blanks from a new auto-map.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, string|null>  $defaults
     * @return array<string, string|null>
     */
    public static function mergeMapping(?array $current, array $defaults): array
    {
        $merged = [];

        foreach (self::TARGETS as $target) {
            $key = $target['key'];
            $existing = is_array($current) ? ($current[$key] ?? null) : null;
            $merged[$key] = is_string($existing) && $existing !== ''
                ? $existing
                : ($defaults[$key] ?? null);
        }

        return $merged;
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private static function matchScore(string $header, array $candidates): int
    {
        $target = self::normalize($header);

        if ($target === '') {
            return 0;
        }

        $best = 0;

        foreach ($candidates as $candidate) {
            $alias = self::normalize($candidate);

            if ($alias === '') {
                continue;
            }

            $score = 0;

            if ($target === $alias) {
                $score = 100;
            } elseif (strlen($alias) >= 3 && (str_starts_with($target, $alias) || str_starts_with($alias, $target))) {
                $score = 60;
            } elseif (strlen($alias) >= 4 && str_contains($target, $alias)) {
                $score = 30;
            }

            if ($score > 0) {
                $best = max($best, $score + strlen($alias));
            }
        }

        return $best;
    }

    private static function normalize(string $value): string
    {
        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $ascii = is_string($folded) && trim($folded) !== '' ? $folded : $value;

        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $ascii));
    }
}
