<?php

namespace App\Enums;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PickupRequest;

enum SupportObjectType: string
{
    case ORDER = 'ORDER';
    case INVOICE = 'INVOICE';
    case PICKUP_REQUEST = 'PICKUP_REQUEST';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    public function label(): string
    {
        return __('support_object_types.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::ORDER => 'ri-shopping-basket-2-line',
            self::INVOICE => 'ri-bill-line',
            self::PICKUP_REQUEST => 'ri-truck-line',
        };
    }

    /**
     * Fully-qualified Eloquent model class backing this object type.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::ORDER => Order::class,
            self::INVOICE => Invoice::class,
            self::PICKUP_REQUEST => PickupRequest::class,
        };
    }

    /**
     * Column on the related model that identifies the owning seller.
     */
    public function ownerColumn(): string
    {
        return match ($this) {
            self::ORDER, self::INVOICE => 'seller_id',
            self::PICKUP_REQUEST => 'created_by',
        };
    }

    /**
     * Column used as the human-readable reference of the related model.
     */
    public function referenceColumn(): string
    {
        return match ($this) {
            self::ORDER => 'tracking_number',
            self::INVOICE => 'invoice_number',
            self::PICKUP_REQUEST => 'reference',
        };
    }

    /**
     * Named route used to deep-link to the related model detail page.
     */
    public function routeName(): string
    {
        return match ($this) {
            self::ORDER => 'orders.show',
            self::INVOICE => 'invoices.show',
            self::PICKUP_REQUEST => 'pickup-requests.show',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
            ],
            self::cases()
        );
    }
}
