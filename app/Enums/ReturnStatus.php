<?php

namespace App\Enums;

/**
 * Reverse logistics lifecycle, ordered from the failed delivery back to the
 * seller's own hub.
 *
 * The parcel travels the delivery leg in reverse: it is dropped at the hub of
 * the city where the delivery failed, rides an inter-city transfer back to the
 * seller's city, then goes out with a driver for the hand-back. Each step is
 * stamped by a different actor, which is what `allowedBy()` encodes.
 */
enum ReturnStatus: string
{
    case CREATED = 'CREATED';
    case RECEIVED_AT_HUB = 'RECEIVED_AT_HUB';
    case IN_TRANSIT_TO_DEPOT = 'IN_TRANSIT_TO_DEPOT';
    case ARRIVED_VENDOR_HUB = 'ARRIVED_VENDOR_HUB';
    case IN_DELIVERY_TO_VENDOR = 'IN_DELIVERY_TO_VENDOR';
    case DELIVERED_TO_VENDOR = 'DELIVERED_TO_VENDOR';
    case CANCELLED = 'CANCELLED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    /**
     * The six statuses that form the happy path, in order.
     *
     * `CANCELLED` is deliberately excluded: it is an exit, not a step, and
     * every consumer that draws the workflow wants a straight line.
     *
     * @return array<int, self>
     */
    public static function pipeline(): array
    {
        return [
            self::CREATED,
            self::RECEIVED_AT_HUB,
            self::IN_TRANSIT_TO_DEPOT,
            self::ARRIVED_VENDOR_HUB,
            self::IN_DELIVERY_TO_VENDOR,
            self::DELIVERED_TO_VENDOR,
        ];
    }

    /**
     * Position in the pipeline, 1-indexed. Null for statuses off the line.
     */
    public function step(): ?int
    {
        $index = array_search($this, self::pipeline(), true);

        return $index === false ? null : $index + 1;
    }

    public function label(): string
    {
        return __('return_statuses.'.$this->value);
    }

    /**
     * Short description of what the step means on the ground.
     */
    public function description(): string
    {
        return __('return_statuses.descriptions.'.$this->value);
    }

    /**
     * Human name of the role expected to stamp this status.
     */
    public function actorLabel(): string
    {
        return __('return_statuses.actors.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'warning',
            self::RECEIVED_AT_HUB => 'info',
            self::IN_TRANSIT_TO_DEPOT => 'primary',
            self::ARRIVED_VENDOR_HUB => 'info',
            self::IN_DELIVERY_TO_VENDOR => 'warning',
            self::DELIVERED_TO_VENDOR => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREATED => 'ri-file-add-line',
            self::RECEIVED_AT_HUB => 'ri-store-3-line',
            self::IN_TRANSIT_TO_DEPOT => 'ri-truck-line',
            self::ARRIVED_VENDOR_HUB => 'ri-building-2-line',
            self::IN_DELIVERY_TO_VENDOR => 'ri-e-bike-2-line',
            self::DELIVERED_TO_VENDOR => 'ri-checkbox-circle-line',
            self::CANCELLED => 'ri-close-circle-line',
        };
    }

    /**
     * Permissions that authorise moving a return *into* this status.
     *
     * Any one of them is enough. `returns.manage` is not listed because it is
     * checked separately as a blanket override, and `returns.update_status`
     * stays in every operational entry so roles provisioned before the
     * per-status permissions existed keep working.
     *
     * @return array<int, string>
     */
    public function allowedBy(): array
    {
        return match ($this) {
            self::CREATED => ['returns.create', 'returns.create_request'],
            self::RECEIVED_AT_HUB => ['returns.transition.to_received_at_hub', 'returns.update_status'],
            self::IN_TRANSIT_TO_DEPOT => ['returns.transition.to_in_transit_to_depot', 'returns.update_status'],
            self::ARRIVED_VENDOR_HUB => ['returns.transition.to_arrived_vendor_hub', 'returns.update_status'],
            self::IN_DELIVERY_TO_VENDOR => ['returns.transition.to_in_delivery_to_vendor', 'returns.update_status'],
            self::DELIVERED_TO_VENDOR => ['returns.transition.to_delivered_to_vendor', 'returns.update_status'],
            self::CANCELLED => ['returns.manage'],
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::DELIVERED_TO_VENDOR, self::CANCELLED], true);
    }

    /**
     * Customer and address overrides are only useful while the parcel can still
     * be re-routed: before it is handed to a hub, and again once it is out for
     * the hand-back to the seller.
     */
    public function allowsCustomerDataEdit(): bool
    {
        return in_array($this, [self::CREATED, self::IN_DELIVERY_TO_VENDOR], true);
    }

    /**
     * Statuses a return must be in to ride an inter-city transfer, and the one
     * the transfer legs move it to.
     */
    public function isTransferable(): bool
    {
        return $this === self::RECEIVED_AT_HUB;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ],
            self::cases()
        );
    }

    /**
     * Full workflow description, shared by the returns screens and the Help
     * Center so the documentation cannot drift from the enforced rules.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function matrix(): array
    {
        return array_map(
            static fn (self $status) => [
                'value' => $status->value,
                'step' => $status->step(),
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->color(),
                'icon' => $status->icon(),
                'actor' => $status->actorLabel(),
                'permissions' => $status->allowedBy(),
                'terminal' => $status->isTerminal(),
                'editable_customer_data' => $status->allowsCustomerDataEdit(),
            ],
            self::cases()
        );
    }
}
