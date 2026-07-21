<?php

namespace App\Listeners;

use App\Enums\UserStatus;
use App\Events\NewSellerRegistered;
use App\Models\User;
use App\Notifications\NewSellerRegistrationNotification;
use App\Services\NotificationDispatcher;

class NotifyAdminOfNewSellerRegistration
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(NewSellerRegistered $event): void
    {
        $admins = User::query()
            ->where('status', UserStatus::Active->value)
            ->where(function ($query) {
                $query->whereHas('roles', fn ($q) => $q->whereIn('name', User::SUPER_ADMIN_ROLES))
                    ->orWhereHas('roles.permissions', fn ($q) => $q->where('name', 'roles.read'));
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
