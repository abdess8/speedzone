<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ReturnTransitionService
{
    /**
     * The reverse logistics graph. Strictly linear: a return never skips a hub,
     * because each step is the physical hand-over that the next actor signs for.
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
     * @return array<int, string>
     */
    public function allowedNextStatuses(OrderReturn $return, User $actor): array
    {
        $current = $return->status instanceof ReturnStatus ? $return->status->value : $return->status;
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];
        $next = [];

        foreach ($allowed as $status) {
            if ($this->canTransitionTo($status, $actor)) {
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
                'status' => 'This return cannot be modified once completed or cancelled.',
            ]);
        }

        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Transition from {$from} to {$to} is not allowed.",
            ]);
        }

        if (! $this->canTransitionTo($to, $actor)) {
            throw new AuthorizationException('You are not allowed to perform this return status transition.');
        }
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
