<?php

namespace App\Enums;

enum NotificationType: string
{
    case InvoiceGenerated = 'invoice_generated';
    case TicketCreated = 'ticket_created';
    case TicketMessage = 'ticket_message';
    case TicketClosed = 'ticket_closed';
    case ReturnRequested = 'return_requested';
    case StockPickupRequested = 'stock_pickup_requested';
    case System = 'system_notifications';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = trans('notifications.types.'.$case->value);
        }

        return $options;
    }
}
