<?php

namespace App\Support;

use App\Enums\NotificationType;

/**
 * Who is entitled to *receive* each kind of notification.
 *
 * Preferences already let a user silence a topic, but a preference is a choice,
 * not a boundary: it starts enabled, so anybody who happened to be in a
 * recipient list read every announcement — a vendor was told a shop had signed
 * up, a driver was told an invoice had been issued. Entitlement is a matter of
 * role, so it is expressed the way every other role-based rule in this codebase
 * is: as a permission, seeded per role and revocable one user at a time.
 *
 * The two gates compose: the permission decides whether the topic concerns you
 * at all, the preference decides whether you want to hear about it.
 */
class NotificationPermissions
{
    public const PREFIX = 'notifications.';

    public static function for(NotificationType $type): string
    {
        return self::PREFIX.$type->value;
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_map(self::for(...), NotificationType::cases());
    }

    /**
     * Administration hears everything: it is the desk complaints land on.
     *
     * @return array<int, string>
     */
    public static function adminDefaults(): array
    {
        return self::all();
    }

    /**
     * Back-office operations: the flow of parcels and the people asking about
     * it. Not billing, and not sign-ups.
     *
     * @return array<int, string>
     */
    public static function dispatcherDefaults(): array
    {
        return self::names([
            NotificationType::StockPickupRequested,
            NotificationType::ReturnRequested,
            NotificationType::TicketCreated,
            NotificationType::TicketMessage,
            NotificationType::TicketClosed,
            NotificationType::System,
        ]);
    }

    /**
     * The field: the round he is asked to make, and nothing about the money the
     * platform bills for it.
     *
     * @return array<int, string>
     */
    public static function driverDefaults(): array
    {
        return self::names([
            NotificationType::StockPickupRequested,
            NotificationType::System,
        ]);
    }

    /**
     * The merchant: his own paperwork and his own conversations.
     *
     * @return array<int, string>
     */
    public static function sellerDefaults(): array
    {
        return self::names([
            NotificationType::InvoiceGenerated,
            NotificationType::TicketCreated,
            NotificationType::TicketMessage,
            NotificationType::TicketClosed,
            NotificationType::System,
        ]);
    }

    /**
     * @param  array<int, NotificationType>  $types
     * @return array<int, string>
     */
    private static function names(array $types): array
    {
        return array_map(self::for(...), $types);
    }
}
