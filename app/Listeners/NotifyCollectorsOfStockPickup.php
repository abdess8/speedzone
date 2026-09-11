<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\StockPickupRequested;
use App\Notifications\StockPickupRequestedNotification;
use App\Services\NotificationDispatcher;
use App\Support\NotificationRecipients;

/**
 * Tell the field that a shop is waiting.
 *
 * Nobody is assigned: everyone able to work that city hears about it and the
 * first to show up signs for the goods. Naming a single collector would mean the
 * round stalls whenever that one person is off, and a stalled round is a vendor
 * whose stock never reaches the catalog.
 */
class NotifyCollectorsOfStockPickup
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(StockPickupRequested $event): void
    {
        $cityId = $event->reception->pickupCityId();

        if ($cityId === null) {
            return;
        }

        $collectors = NotificationRecipients::query(NotificationType::StockPickupRequested)
            ->coveringCity($cityId)
            ->get();

        if ($collectors->isEmpty()) {
            return;
        }

        $this->dispatcher->send(
            $collectors,
            new StockPickupRequestedNotification($event->reception),
        );
    }
}
