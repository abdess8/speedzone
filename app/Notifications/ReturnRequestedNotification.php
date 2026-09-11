<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\OrderReturn;
use App\Models\User;

class ReturnRequestedNotification extends AppNotification
{
    public function __construct(public readonly OrderReturn $return) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::ReturnRequested;
    }

    protected function authorizes(User $notifiable): bool
    {
        return $notifiable->can('view', $this->return);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->return->loadMissing(['order.seller', 'createdBy']);

        $trackingNumber = $this->return->order?->tracking_number ?? '—';
        $sellerName = $this->return->order?->seller?->name
            ?? $this->return->createdBy?->name
            ?? trans('notifications.unknown_user');

        return $this->buildPayload([
            'title' => trans('notifications.titles.return_requested'),
            'message' => trans('notifications.messages.return_requested'),
            'reference' => $this->return->reference,
            'tracking_number' => $trackingNumber,
            'seller' => $sellerName,
            'url' => route('returns.show', $this->return->id),
            'return_id' => $this->return->id,
        ]);
    }
}
