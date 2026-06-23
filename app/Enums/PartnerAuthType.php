<?php

namespace App\Enums;

enum PartnerAuthType: string
{
    case BASIC = 'BASIC';
    case BEARER = 'BEARER';
    case API_KEY = 'API_KEY';
    case LOGIN_TOKEN = 'LOGIN_TOKEN';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    public static function resolve(self|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) $value) ?? self::BASIC;
    }

    public function label(): string
    {
        return __('partner_auth_types.'.$this->value);
    }

    public function description(): string
    {
        return __('partner_auth_types.descriptions.'.$this->value);
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            self::cases()
        );
    }
}
