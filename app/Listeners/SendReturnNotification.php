<?php

namespace App\Listeners;

use App\Enums\ReturnInitiatedByRole;
use App\Events\ReturnRequested;
use App\Models\User;
use App\Notifications\ReturnRequestedNotification;
use App\Services\NotificationDispatcher;

class SendReturnNotification
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(ReturnRequested $event): void
    {
        if ($event->role !== ReturnInitiatedByRole::SELLER) {
            return;
        }

        $admins = User::query()
            ->where(function ($query) {
                $query->whereHas('roles.permissions', fn ($q) => $q->where('name', 'returns.read.all'))
                    ->orWhereHas('roles', fn ($q) => $q->whereIn('name', User::SUPER_ADMIN_ROLES));
            })
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $this->dispatcher->send(
            $admins,
            new ReturnRequestedNotification($event->return),
        );
    }
}
