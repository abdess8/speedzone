<?php

namespace App\Enums;

enum SellerPaymentMethod: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case CHEQUE = 'cheque';
    case CASH = 'cash';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $method) => $method->value, self::cases());
    }

    public function label(): string
    {
        return __('seller_payment_methods.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'ri-bank-line',
            self::CHEQUE => 'ri-bill-line',
            self::CASH => 'ri-money-dollar-circle-line',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $method) => [
                'value' => $method->value,
                'label' => $method->label(),
                'icon' => $method->icon(),
            ],
            self::cases()
        );
    }
}
