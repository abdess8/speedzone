<?php

namespace App\Enums;

enum AlertFormat: string
{
    /** Opens over the interface on the first page load of a session. */
    case MODAL = 'modal';

    /** Sits at the top of the content area on every page. */
    case BANNER = 'banner';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $format) => $format->value, self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $format) => [
            'value' => $format->value,
            'label' => $format->label(),
            'description' => __('alerts.formats.'.$format->value.'_hint'),
            'icon' => $format->icon(),
        ], self::cases());
    }

    public function label(): string
    {
        return __('alerts.formats.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::MODAL => 'ri-window-2-line',
            self::BANNER => 'ri-layout-top-line',
        };
    }
}
