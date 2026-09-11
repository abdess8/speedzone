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
 * Three gates compose:
 *  - the topic permission decides whether announcements of this kind concern
 *    the role at all;
 *  - the resource permission decides whether the user may actually see the
 *    underlying object (a vendor without `users.read` is not told a shop
 *    signed up, even if someone handed him the topic grant);
 *  - the preference decides whether he wants to hear about it.
 */
class NotificationPermissions
{
    public const PREFIX = 'notifications.';

    public static function for(NotificationType $type): string
    {
        return self::PREFIX.$type->value;
    }

    /**
     * Operational grants that unlock the object a notification is about.
     *
     * Empty means the topic itself is the only RBAC check (system notices),
     * and the notification class still decides whether *this* row is visible.
     *
     * @return array<int, string>
     */
    public static function resourcePermissions(NotificationType $type): array
    {
        return match ($type) {
            NotificationType::SellerRegistered => ['users.read'],
            NotificationType::InvoiceGenerated => ['invoices.read.own', 'invoices.read.all'],
            NotificationType::TicketCreated,
            NotificationType::TicketMessage,
            NotificationType::TicketClosed => SupportPermissions::moduleAccess(),
            NotificationType::ReturnRequested => ['returns.read.all', 'returns.manage'],
            NotificationType::StockPickupRequested => [StockPermissions::COLLECT_INBOUND],
            NotificationType::System => [],
        };
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
