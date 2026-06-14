<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CARD_PAYMENT = 'CARD_PAYMENT';
    case CASH = 'CASH';

    /**
     * Resolve a stored value, mapping legacy COD to card payment.
     */
    public static function resolve(string $value): self
    {
        if ($value === 'COD') {
            return self::CARD_PAYMENT;
        }

        return self::from($value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $method) => $method->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::CARD_PAYMENT => 'Card Payment',
            self::CASH => 'Cash',
        };
    }

    /**
     * Remix Icon class used in the order form selector and badges.
     */
    public function icon(): string
    {
        return match ($this) {
            self::CARD_PAYMENT => 'ri-bank-card-fill',
            self::CASH => 'ri-money-dollar-box-fill',
        };
    }

    /**
     * Emoji prefix used on labels, lists, and PDF output.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::CARD_PAYMENT => '💳',
            self::CASH => '💵',
        };
    }

    /**
     * Human-readable label with emoji prefix.
     */
    public function displayLabel(): string
    {
        return $this->emoji().' '.$this->label();
    }

    /**
     * Bootstrap contextual colour used by badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::CARD_PAYMENT => 'primary',
            self::CASH => 'success',
        };
    }

    /**
     * Whether the delivery driver must collect cash from the customer.
     */
    public function requiresCashCollection(): bool
    {
        return $this === self::CASH;
    }

    /**
     * Amount the driver must collect from the customer, if applicable.
     */
    public function amountToCollect(?float $orderAmount): ?float
    {
        if (! $this->requiresCashCollection() || $orderAmount === null) {
            return null;
        }

        return $orderAmount;
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string, emoji: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $method) => [
                'value' => $method->value,
                'label' => $method->label(),
                'icon' => $method->icon(),
                'emoji' => $method->emoji(),
                'color' => $method->color(),
            ],
            self::cases()
        );
    }
}
