<?php

namespace App\Services\Partners;

use App\Enums\OrderStatus;
use App\Enums\PartnerUpdateField;
use App\Models\Order;
use App\Models\Partner;
use App\Models\UpdateFieldMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Builds outbound payloads and pushes partner delivery status updates.
 */
class PartnerOutboundSyncService
{
    public function __construct(
        private readonly PartnerApiService $partnerApi,
        private readonly PartnerStatusMapper $statusMapper,
    ) {}

    /**
     * Whether this order should be synced to the partner before a local status change.
     */
    public function shouldSync(Order $order): bool
    {
        if (! $order->partner_id || blank($order->external_tracking_code)) {
            return false;
        }

        $partner = $this->resolvePartner($order);

        return $partner !== null
            && $partner->sync_status
            && $partner->is_active
            && filled($partner->endpoint_update);
    }

    /**
     * Push a status update to the partner API before applying the change locally.
     *
     * @throws PartnerApiException
     * @throws ValidationException when no status mapping exists
     */
    public function pushStatusChange(Order $order, string $targetStatus, ?string $comment = null): void
    {
        if (! $this->shouldSync($order)) {
            return;
        }

        $partner = $this->resolvePartner($order);
        $payload = $this->buildPayload($order, $partner, $targetStatus, $comment);

        $this->partnerApi->pushStatusUpdate($partner, $payload);
    }

    /**
     * Record a sync failure on the order and raise a validation error for the UI.
     *
     * @throws ValidationException
     */
    public function recordFailure(Order $order, PartnerApiException $exception): never
    {
        $message = $exception->getMessage();

        $order->update(['partner_sync_error' => $message]);

        throw ValidationException::withMessages([
            'status' => $message,
        ]);
    }

    public function clearSyncError(Order $order): void
    {
        if ($order->partner_sync_error !== null) {
            $order->update(['partner_sync_error' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(
        Order $order,
        Partner $partner,
        string $targetStatus,
        ?string $comment = null,
    ): array {
        $mappings = $this->updateFieldMappings($partner);

        if ($mappings->isEmpty()) {
            return $this->defaultPayload($order, $partner, $targetStatus, $comment);
        }

        $values = $this->resolveFieldValues($order, $partner, $targetStatus, $comment);
        $payload = [];

        foreach ($mappings as $mapping) {
            $field = $mapping->speedzone_field instanceof PartnerUpdateField
                ? $mapping->speedzone_field
                : PartnerUpdateField::from($mapping->speedzone_field);

            if (! array_key_exists($field->value, $values)) {
                continue;
            }

            $payload[$mapping->partner_field] = $values[$field->value];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPayload(
        Order $order,
        Partner $partner,
        string $targetStatus,
        ?string $comment,
    ): array {
        $partnerStatus = $this->resolvePartnerStatus($partner, $targetStatus);

        return array_filter([
            'id' => $order->external_tracking_code,
            'status' => $partnerStatus,
            'message' => $comment ?? '',
            'proof_image' => '',
            'deliver_by' => $this->resolveDeliveredAt($order, $targetStatus),
            'isDeliveredPartial' => 0,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFieldValues(
        Order $order,
        Partner $partner,
        string $targetStatus,
        ?string $comment,
    ): array {
        return [
            PartnerUpdateField::EXTERNAL_TRACKING_CODE->value => $order->external_tracking_code,
            PartnerUpdateField::TRACKING_NUMBER->value => $order->tracking_number,
            PartnerUpdateField::PARTNER_STATUS->value => $this->resolvePartnerStatus($partner, $targetStatus),
            PartnerUpdateField::STATUS_COMMENT->value => $comment ?? '',
            PartnerUpdateField::PROOF_IMAGE->value => '',
            PartnerUpdateField::DELIVERED_AT->value => $this->resolveDeliveredAt($order, $targetStatus),
            PartnerUpdateField::IS_DELIVERED_PARTIAL->value => 0,
        ];
    }

    private function resolvePartnerStatus(Partner $partner, string $targetStatus): ?string
    {
        $status = OrderStatus::tryFrom($targetStatus);

        if ($status === null) {
            return null;
        }

        $partnerStatus = $this->statusMapper->toPartnerStatus($partner, $status);

        if ($partnerStatus === null) {
            Log::warning('Partner outbound sync skipped: no status mapping.', [
                'partner_id' => $partner->id,
                'speedzone_status' => $status->value,
            ]);

            throw ValidationException::withMessages([
                'status' => __('partners.orders.sync.no_status_mapping', ['status' => $status->label()]),
            ]);
        }

        return $partnerStatus;
    }

    private function resolveDeliveredAt(Order $order, string $targetStatus): ?string
    {
        if ($targetStatus !== OrderStatus::DELIVERED->value) {
            return null;
        }

        $date = $order->delivered_at ?? now();

        return $date->format('Y-m-d');
    }

    /**
     * @return Collection<int, UpdateFieldMapping>
     */
    private function updateFieldMappings(Partner $partner): Collection
    {
        if ($partner->relationLoaded('updateFieldMappings')) {
            return $partner->updateFieldMappings;
        }

        return $partner->updateFieldMappings()->get();
    }

    private function resolvePartner(Order $order): ?Partner
    {
        if ($order->relationLoaded('partner')) {
            return $order->partner;
        }

        return Partner::query()->find($order->partner_id);
    }
}
