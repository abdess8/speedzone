<?php

namespace App\Services\Partners;

use App\Enums\OrderStatus;
use App\Models\Partner;
use App\Models\StatusMapping;
use Illuminate\Support\Collection;

/**
 * Translates statuses between speedZone (App\Enums\OrderStatus) and a partner's
 * own vocabulary using the per-partner status_mappings table.
 *
 *  - Outbound (push):     speedZone OrderStatus -> partner status string
 *  - Inbound (ingestion): partner status string -> speedZone OrderStatus
 */
class PartnerStatusMapper
{
    /**
     * Per-partner mapping cache to avoid repeated queries within one request.
     *
     * @var array<int, Collection<int, StatusMapping>>
     */
    private array $cache = [];

    /**
     * Resolve the partner's status string for one of our OrderStatus values.
     */
    public function toPartnerStatus(Partner $partner, OrderStatus|string $speedzoneStatus): ?string
    {
        $value = $speedzoneStatus instanceof OrderStatus
            ? $speedzoneStatus->value
            : $speedzoneStatus;

        return $this->mappings($partner)
            ->first(fn (StatusMapping $mapping) => $mapping->speedzone_status?->value === $value)
            ?->partner_status;
    }

    /**
     * Resolve our OrderStatus for an incoming partner status string.
     * Comparison is case-insensitive to tolerate partner formatting.
     */
    public function toSpeedzoneStatus(Partner $partner, string $partnerStatus): ?OrderStatus
    {
        return $this->mappings($partner)
            ->first(fn (StatusMapping $mapping) => strcasecmp($mapping->partner_status, $partnerStatus) === 0)
            ?->speedzone_status;
    }

    /**
     * Load (and memoize) every mapping for a partner.
     *
     * @return Collection<int, StatusMapping>
     */
    private function mappings(Partner $partner): Collection
    {
        return $this->cache[$partner->id] ??= $partner->statusMappings()->get();
    }

    /**
     * Forget cached mappings (e.g. after an admin edits them).
     */
    public function flush(?Partner $partner = null): void
    {
        if ($partner === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[$partner->id]);
    }
}
