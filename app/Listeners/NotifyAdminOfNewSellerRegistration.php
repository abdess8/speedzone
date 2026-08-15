<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Events\NewSellerRegistered;
use App\Models\User;
use App\Notifications\NewSellerRegistrationNotification;
use App\Services\NotificationDispatcher;
use App\Support\NotificationPermissions;

class NotifyAdminOfNewSellerRegistration
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(NewSellerRegistered $event): void
    {
        // Addressed by the notification grant rather than by `roles.read`: an
        // account allowed to read the role list is not necessarily the desk
        // that approves shops.
        $permission = NotificationPermissions::for(NotificationType::SellerRegistered);

        $admins = User::query()
            ->where('status', UserStatus::Active->value)
            ->where(function ($query) use ($permission) {
                $query->whereHas('roles', fn ($q) => $q->whereIn('name', User::SUPER_ADMIN_ROLES))
                    ->orWhereHas('roles.permissions', fn ($q) => $q->where('name', $permission))
                    ->orWhereHas('permissions', fn ($q) => $q->where('name', $permission));
            })
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $this->dispatcher->send(
            $admins,
            new NewSellerRegistrationNotification($event->user),
        );
    }
}
