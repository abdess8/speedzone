<?php

namespace App\Policies;

use App\Enums\StockReceptionStatus;
use App\Models\StockReception;
use App\Models\User;
use App\Support\StockPermissions;

/**
 * Inbound shipments.
 *
 * The document changes hands three times, and each pair of hands may only do its
 * own half: the vendor declares what he is shipping, a collector signs for what he
 * loads, the depot signs for what arrives. That separation is the only thing that
 * makes the three figures worth comparing — a party allowed to write two of them
 * could hide a loss between them.
 *
 * Geography is part of the rule, not decoration. A collector is offered the shops
 * he can actually drive to, and an agent closes the shipments addressed to his own
 * depot; the admin override exists for the cases where somebody has to reach
 * across a city anyway.
 */
class StockReceptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(StockPermissions::VIEW)
            || $user->hasPermission(StockPermissions::CREATE_INBOUND)
            || $user->hasPermission(StockPermissions::COLLECT_INBOUND)
            || $user->hasPermission(StockPermissions::RECEIVE_INBOUND)
            || $user->hasPermission(StockPermissions::ADMIN_OVERRIDE);
    }

    public function view(User $user, StockReception $reception): bool
    {
        if ($user->hasPermission(StockPermissions::RECEIVE_INBOUND)
            || $user->hasPermission(StockPermissions::ADMIN_OVERRIDE)) {
            return true;
        }

        // A collector needs to read the slip he is driving to, or has driven, even
        // once it has moved past him: his own count is on it.
        if ($user->hasPermission(StockPermissions::COLLECT_INBOUND)
            && $this->isReachableCollector($user, $reception)) {
            return true;
        }

        return $this->viewAny($user) && $this->belongsToActor($user, $reception);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(StockPermissions::CREATE_INBOUND);
    }

    /**
     * Correcting the slip before anybody is sent for it.
     *
     * Once it is in the pickup queue the declaration is frozen: it is the document
     * the collector will count against.
     */
    public function update(User $user, StockReception $reception): bool
    {
        return $user->hasPermission(StockPermissions::CREATE_INBOUND)
            && $this->belongsToActor($user, $reception)
            && $reception->isEditableByVendor();
    }

    /**
     * Asking us to come and get the parcel.
     */
    public function send(User $user, StockReception $reception): bool
    {
        return $this->update($user, $reception);
    }

    /**
     * Going to the shop and signing for what is handed over.
     *
     * Restricted to the cities the collector actually works, so the queue he is
     * offered is a round he can drive rather than the national backlog.
     */
    public function collect(User $user, StockReception $reception): bool
    {
        return $user->hasPermission(StockPermissions::COLLECT_INBOUND)
            && $reception->statusEnum()->canTransitionTo(StockReceptionStatus::COLLECTED)
            && $this->coversPickupCity($user, $reception);
    }

    /**
     * Putting the goods on the road to the depot.
     *
     * Only the person holding them, because that is who can say they left. Hub
     * staff keep the door open for the round that ends with the van in the yard and
     * the collector already gone home.
     */
    public function dispatch(User $user, StockReception $reception): bool
    {
        if (! $reception->statusEnum()->canTransitionTo(StockReceptionStatus::IN_TRANSIT)) {
            return false;
        }

        if ($user->hasPermission(StockPermissions::RECEIVE_INBOUND)
            || $user->hasPermission(StockPermissions::ADMIN_OVERRIDE)) {
            return true;
        }

        return $user->hasPermission(StockPermissions::COLLECT_INBOUND)
            && (int) $reception->collected_by === $user->id;
    }

    /**
     * Counting the parcel in and crediting the catalog. Depot side only.
     *
     * Restricted to a shipment actually on the road, so a document that has not
     * left the shop cannot be credited from a desk, and one already closed can be
     * credited neither a second time nor offered a counting sheet by the detail
     * screen.
     */
    public function receive(User $user, StockReception $reception): bool
    {
        return $user->hasPermission(StockPermissions::RECEIVE_INBOUND)
            && $reception->statusEnum()->canTransitionTo(StockReceptionStatus::VALIDATED)
            && $this->worksDestinationDepot($user, $reception);
    }

    /**
     * Calling the shipment off.
     *
     * The vendor may do it while the parcel is still on his own counter. Once a
     * collector has loaded it the goods are in our hands, and only we can account
     * for them — a vendor cancelling at that point would erase a hand-over that
     * physically happened.
     */
    public function cancel(User $user, StockReception $reception): bool
    {
        $status = $reception->statusEnum();

        if ($status->isTerminal()) {
            return false;
        }

        if ($user->hasPermission(StockPermissions::ADMIN_OVERRIDE)) {
            return true;
        }

        if ($user->hasPermission(StockPermissions::RECEIVE_INBOUND)) {
            return true;
        }

        if ($status->isInOurHands()) {
            return $user->hasPermission(StockPermissions::COLLECT_INBOUND)
                && (int) $reception->collected_by === $user->id;
        }

        return $user->hasPermission(StockPermissions::CREATE_INBOUND)
            && $this->belongsToActor($user, $reception);
    }

    private function belongsToActor(User $user, StockReception $reception): bool
    {
        return (int) $reception->seller_id === $user->accountOwnerId();
    }

    /**
     * Whether this collector works the city the parcel has to be picked up in.
     */
    private function coversPickupCity(User $user, StockReception $reception): bool
    {
        return $this->worksCity($user, $reception->pickupCityId());
    }

    /**
     * Whether this collector is the one who took the parcel, or could still take it.
     */
    private function isReachableCollector(User $user, StockReception $reception): bool
    {
        return (int) $reception->collected_by === $user->id
            || $this->coversPickupCity($user, $reception);
    }

    /**
     * Whether this agent works the depot the parcel is addressed to.
     */
    private function worksDestinationDepot(User $user, StockReception $reception): bool
    {
        return $this->worksCity($user, $reception->destination_city_id);
    }

    /**
     * Whether a city is within this user's reach.
     *
     * Two blanks both mean "geography says nothing here" and are answered the same
     * way — by not narrowing:
     *  - a shop or depot with no city cannot be routed to anybody in particular, so
     *    it stays reachable by everyone rather than by nobody;
     *  - a user attached to no city at all is central staff, not somebody standing
     *    in the wrong town. Locking him out of every shipment would leave a shop
     *    waiting on a queue no one is allowed to work.
     *
     * A user who *is* placed somewhere is held to it, which is the point.
     */
    private function worksCity(User $user, int|string|null $cityId): bool
    {
        if ($cityId === null || $user->hasPermission(StockPermissions::ADMIN_OVERRIDE)) {
            return true;
        }

        $reach = $user->cityIds();

        return $reach === [] || in_array((int) $cityId, $reach, true);
    }
}
