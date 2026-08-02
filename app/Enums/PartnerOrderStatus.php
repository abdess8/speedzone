<?php

namespace App\Enums;

/**
 * Strict vocabulary of speedZone statuses allowed on B2B partner deliveries.
 *
 * These map to partner-facing labels (e.g. Sendit) through status_mappings.
 */
enum PartnerOrderStatus: string
{
    case IN_TRANSIT = 'IN_TRANSIT';
    case RECEIVED_IN_DESTINATION = 'RECEIVED_IN_DESTINATION';
    case OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    case DELIVERED = 'DELIVERED';
    case FAILED = 'FAILED';
    case REJECTED = 'REJECTED';
    case CANCELED = 'CANCELED';

    /**
     * Partner-facing French labels used in Sendit and similar integrations.
     */
    public function partnerLabel(): string
    {
        return match ($this) {
            self::IN_TRANSIT => 'En transit',
            self::RECEIVED_IN_DESTINATION => 'Distribue',
            self::OUT_FOR_DELIVERY => 'En cours de livraison',
            self::DELIVERED => 'livree',
            self::FAILED => 'en attente de retour',
            self::REJECTED => 'Rejeté',
            self::CANCELED => 'Annulé',
        };
    }

    public function toOrderStatus(): OrderStatus
    {
        return OrderStatus::from($this->value);
    }

    public static function tryFromOrderStatus(OrderStatus|string $status): ?self
    {
        $value = $status instanceof OrderStatus ? $status->value : $status;

        return self::tryFrom($value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public static function isAllowed(OrderStatus|string $status): bool
    {
        return self::tryFromOrderStatus($status) !== null;
    }

    /**
     * @return array<int, array{value: string, label: string, partner_label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status) => [
                'value' => $status->value,
                'label' => $status->toOrderStatus()->label(),
                'partner_label' => $status->partnerLabel(),
            ],
            self::cases()
        );
    }
}
