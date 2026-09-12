<?php

namespace App\Enums;

/**
 * Storefronts a vendor can plug into the platform.
 *
 * Availability is a product decision, not a permission: a grant can exist
 * (so a team role is ready the day the connector ships) while the catalogue
 * still shows the card as coming soon.
 */
enum EcommercePlatform: string
{
    case YouCan = 'youcan';
    case Shopify = 'shopify';
    case WooCommerce = 'woocommerce';
    case PrestaShop = 'prestashop';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $platform) => $platform->value, self::cases());
    }

    public static function tryFromMixed(self|string|null $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) $value);
    }

    /**
     * The connector has a working connection form, not just a catalogue card.
     */
    public function isAvailable(): bool
    {
        return $this === self::YouCan;
    }

    public function icon(): string
    {
        return match ($this) {
            self::YouCan => 'ri-store-2-fill',
            self::Shopify => 'ri-shopping-bag-3-fill',
            self::WooCommerce => 'ri-shopping-cart-2-fill',
            self::PrestaShop => 'ri-store-3-fill',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::YouCan => '#6a4cff',
            self::Shopify => '#95bf47',
            self::WooCommerce => '#7f54b3',
            self::PrestaShop => '#df0067',
        };
    }

    public function name(): string
    {
        return match ($this) {
            self::YouCan => 'YouCan',
            self::Shopify => 'Shopify',
            self::WooCommerce => 'WooCommerce',
            self::PrestaShop => 'PrestaShop',
        };
    }

    /**
     * SpeedZone screen that manages this connector for a given shop.
     */
    public function manageUrl(?int $storeId = null): string
    {
        return match ($this) {
            self::YouCan => route('integrations.youcan', array_filter([
                'store_id' => $storeId,
            ])),
            default => route('integrations.index', ['platform' => $this->value]),
        };
    }
}
