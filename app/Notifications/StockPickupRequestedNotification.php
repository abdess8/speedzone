<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\StockReception;

class StockPickupRequestedNotification extends AppNotification
{
    public function __construct(public readonly StockReception $reception) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::StockPickupRequested;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->reception->loadMissing(['store.city', 'seller.city', 'destinationCity', 'items']);

        $shopCity = $this->reception->store?->city?->name
            ?? $this->reception->seller?->city?->name;

        return $this->buildPayload([
            'title' => trans('notifications.titles.stock_pickup_requested'),
            'message' => trans('notifications.messages.stock_pickup_requested', [
                'shop' => $this->reception->store?->name
                    ?? $this->reception->seller?->name
                    ?? trans('notifications.unknown_user'),
                'city' => $shopCity ?? '—',
            ]),
            'reference' => $this->reception->reference,
            'units' => $this->reception->items->sum('quantity_sent'),
            'pickup_city' => $shopCity,
            'destination_city' => $this->reception->destinationCity?->name,
            'url' => route('stock-receptions.show', $this->reception->id),
            'stock_reception_id' => $this->reception->id,
        ]);
    }
}
