<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnTransitionService
{
    /**
     * The reverse logistics graph. Almost linear: a return never skips a hub,
     * because each step is the physical hand-over that the next actor signs for.
     *
     * The one shortcut is RECEIVED_AT_HUB → ARRIVED_VENDOR_HUB, for the parcel
     * that failed delivery in the seller's own city and therefore has no
     * inter-city leg to ride. It is gated on the parcel actually being there —
     * see {@see self::structuralError()}.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        ReturnStatus::CREATED->value => [
            ReturnStatus::RECEIVED_AT_HUB->value,
            ReturnStatus::CANCELLED->value,
        ],
        ReturnStatus::RECEIVED_AT_HUB->value => [
            ReturnStatus::IN_TRANSIT_TO_DEPOT->value,
            ReturnStatus::ARRIVED_VENDOR_HUB->value,
            ReturnStatus::CANCELLED->value,
        ],
        ReturnStatus::IN_TRANSIT_TO_DEPOT->value => [
            ReturnStatus::ARRIVED_VENDOR_HUB->value,
        ],
        ReturnStatus::ARRIVED_VENDOR_HUB->value => [
            ReturnStatus::IN_DELIVERY_TO_VENDOR->value,
        ],
        ReturnStatus::IN_DELIVERY_TO_VENDOR->value => [
            ReturnStatus::DELIVERED_TO_VENDOR->value,
        ],
        ReturnStatus::DELIVERED_TO_VENDOR->value => [],
        ReturnStatus::CANCELLED->value => [],
    ];

    public function __construct(private readonly ReturnService $returns) {}

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function transition(
        OrderReturn $return,
        ReturnStatus|string $toStatus,
        User $actor,
        ?string $comment = null,
        ?int $locationCityId = null,
    ): OrderReturn {
        $to = $toStatus instanceof ReturnStatus ? $toStatus->value : $toStatus;
        $from = $return->status instanceof ReturnStatus ? $return->status->value : $return->status;

        $this->assertTransitionAllowed($return, $from, $to, $actor);

        return $this->returns->applyStatus(
            $return,
            ReturnStatus::from($to),
            $actor,
            $comment,
            $from,
            $locationCityId,
        );
    }

    /**
     * Hub shortcut: the destination hub signs for the parcel the driver just
     * dropped off. CREATED → RECEIVED_AT_HUB.
     */
    public function receiveAtHub(OrderReturn $return, User $actor, ?string $comment = null, ?int $cityId = null): OrderReturn
    {
        return $this->transition(
            $return,
            ReturnStatus::RECEIVED_AT_HUB,
            $actor,
            $comment ?? 'Dropped off at the delivery city hub.',
            $cityId,
        );
    }

    /**
     * Hand the parcel to a driver for the last mile.
     *
     * Naming the driver and moving the return are one act, not two: a return
     * out for restitution with nobody on it is exactly the state this whole
     * feature exists to prevent, so they share a transaction.
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function handBack(
        OrderReturn $return,
        User $actor,
        User $driver,
        ?string $comment = null,
    ): OrderReturn {
        return DB::transaction(function () use ($return, $actor, $driver, $comment): OrderReturn {
            $this->returns->assignDriver($return, $driver, $actor);

            return $this->transition(
                $return->refresh(),
                ReturnStatus::IN_DELIVERY_TO_VENDOR,
                $actor,
                $comment ?? "Out for hand-back to the vendor with {$driver->full_name}.",
            );
        });
    }

    /**
     * @return array<int, string>
     */
    public function allowedNextStatuses(OrderReturn $return, User $actor): array
    {
        $current = $return->status instanceof ReturnStatus ? $return->status->value : $return->status;
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];
        $next = [];

        foreach ($allowed as $status) {
            if ($this->structuralError($return, $current, $status) !== null) {
                continue;
            }

            if ($this->canTransitionTo($status, $actor) && $this->canActOnStep($return, $status, $actor)) {
                $next[] = $status;
            }
        }

        return $next;
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    private function assertTransitionAllowed(OrderReturn $return, string $from, string $to, User $actor): void
    {
        if ($return->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => __('returns.errors.terminal'),
            ]);
        }

        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('returns.errors.transition_not_allowed', ['from' => $from, 'to' => $to]),
            ]);
        }

        $structural = $this->structuralError($return, $from, $to);

        if ($structural !== null) {
            throw ValidationException::withMessages(['status' => $structural]);
        }

        // Checked here rather than in structuralError(): the step must stay
        // *offered*, since the screen that offers it is where the driver gets
        // picked. Hiding it would leave the parcel with no way forward.
        if ($to === ReturnStatus::IN_DELIVERY_TO_VENDOR->value && $return->assigned_to === null) {
            throw ValidationException::withMessages([
                'driver_id' => __('returns.errors.driver_required'),
            ]);
        }

        if (! $this->canTransitionTo($to, $actor)) {
            throw new AuthorizationException(__('returns.errors.transition_forbidden'));
        }

        if (! $this->canActOnStep($return, $to, $actor)) {
            throw new AuthorizationException(__('returns.errors.not_assigned_driver'));
        }
    }

    /**
     * Rules the graph alone cannot express, checked before permissions so the
     * UI can hide a step that is impossible rather than merely forbidden.
     */
    private function structuralError(OrderReturn $return, string $from, string $to): ?string
    {
        // Skipping the transfer is only honest when there is nothing to
        // transfer: the parcel is already in the city the seller sits in.
        if ($from === ReturnStatus::RECEIVED_AT_HUB->value
            && $to === ReturnStatus::ARRIVED_VENDOR_HUB->value
            && ! $return->isAtVendorCity()) {
            return __('returns.errors.transfer_required');
        }

        return null;
    }

    /**
     * The last leg belongs to the driver who signed for the parcel. Anyone else
     * closing it would be certifying a hand-over he did not witness.
     */
    private function canActOnStep(OrderReturn $return, string $to, User $actor): bool
    {
        if ($to !== ReturnStatus::DELIVERED_TO_VENDOR->value) {
            return true;
        }

        if ($return->assigned_to === null || (int) $return->assigned_to === $actor->id) {
            return true;
        }

        return $actor->hasPermission('returns.manage');
    }

    /**
     * Each step names the permissions that unlock it, so a hub manager who may
     * sign parcels in cannot also close the return on the seller's doorstep.
     */
    private function canTransitionTo(string $to, User $actor): bool
    {
        if ($actor->hasPermission('returns.manage')) {
            return true;
        }

        foreach (ReturnStatus::from($to)->allowedBy() as $permission) {
            if ($actor->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
