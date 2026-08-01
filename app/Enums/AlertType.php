<?php

namespace App\Enums;

enum AlertType: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case DANGER = 'danger';
    case SUCCESS = 'success';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    /**
     * The type, its label and its styling, for the admin picker and the badges.
     *
     * @return array<int, array{value: string, label: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'color' => $type->color(),
            'icon' => $type->icon(),
        ], self::cases());
    }

    public function label(): string
    {
        return __('alerts.types.'.$this->value);
    }

    /** Bootstrap contextual colour. The enum names already follow it. */
    public function color(): string
    {
        return $this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::INFO => 'ri-information-line',
            self::WARNING => 'ri-alert-line',
            self::DANGER => 'ri-error-warning-line',
            self::SUCCESS => 'ri-checkbox-circle-line',
        };
    }
}
