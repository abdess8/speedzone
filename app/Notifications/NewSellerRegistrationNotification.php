<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;

class NewSellerRegistrationNotification extends AppNotification
{
    public function __construct(public readonly User $seller) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::SellerRegistered;
    }

    protected function authorizes(User $notifiable): bool
    {
        return $notifiable->can('viewAny', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->seller->loadMissing('city');

        return $this->buildPayload([
            'title' => trans($this->seller->isDriver()
                ? 'notifications.titles.new_driver_registration'
                : 'notifications.titles.new_seller_registration'),
            'message' => trans($this->seller->isDriver()
                ? 'notifications.messages.new_driver_registration'
                : 'notifications.messages.new_seller_registration'),
            'seller_name' => $this->seller->full_name,
            'email' => $this->seller->email,
            'phone' => $this->seller->phone_number,
            'city' => $this->seller->city?->name,
            'url' => route('admin.pending-users.index'),
        ]);
    }
}
