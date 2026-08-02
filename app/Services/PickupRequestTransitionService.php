<?php

namespace App\Services;

use App\Enums\PickupRequestStatus;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class PickupRequestTransitionService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        PickupRequestStatus::WAITING_FOR_PICKUP->value => [
            PickupRequestStatus::PICKED_UP->value,
            PickupRequestStatus::CANCELLED->value,
        ],
        PickupRequestStatus::PICKED_UP->value => [
            PickupRequestStatus::IN_DEPOT->value,
        ],
        PickupRequestStatus::IN_DEPOT->value => [],
        PickupRequestStatus::CANCELLED->value => [],
    ];

    /**
     * Role-specific transitions (subset of ALLOWED_TRANSITIONS).
     *
     * @var array<string, array<int, string>>
     */
    private const DRIVER_TRANSITIONS = [
        PickupRequestStatus::WAITING_FOR_PICKUP->value => [
            PickupRequestStatus::PICKED_UP->value,
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ADMIN_TRANSITIONS = [
        PickupRequestStatus::PICKED_UP->value => [
            PickupRequestStatus::IN_DEPOT->value,
        ],
        PickupRequestStatus::WAITING_FOR_PICKUP->value => [
            PickupRequestStatus::CANCELLED->value,
        ],
    ];

    public function __construct(private readonly PickupRequestService $pickupRequests) {}

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function transition(
        PickupRequest $pickup,
        PickupRequestStatus|string $toStatus,
        User $actor,
        ?string $comment = null
    ): PickupRequest {
        $to = $toStatus instanceof PickupRequestStatus ? $toStatus->value : $toStatus;
        $from = $pickup->status instanceof PickupRequestStatus ? $pickup->status->value : $pickup->status;

        $this->assertTransitionAllowed($pickup, $from, $to, $actor);

        return $this->pickupRequests->applyStatus($pickup, PickupRequestStatus::from($to), $actor, $comment, $from);
    }

    /**
     * @return array<int, string>
     */
    public function allowedNextStatuses(PickupRequest $pickup, User $actor): array
    {
        $current = $pickup->status instanceof PickupRequestStatus ? $pickup->status->value : $pickup->status;
        $next = [];

        if ($current === PickupRequestStatus::WAITING_FOR_PICKUP->value) {
            if ($actor->hasPermission('pickup_requests.pickup')
                && ($pickup->assigned_to === $actor->id || $actor->hasPermission('pickup_requests.change_status'))) {
                $next[] = PickupRequestStatus::PICKED_UP->value;
            }

            if ($actor->hasPermission('pickup_requests.change_status')) {
                $next[] = PickupRequestStatus::CANCELLED->value;
            }
        }

        if ($current === PickupRequestStatus::PICKED_UP->value && $actor->hasPermission('pickup_requests.change_status')) {
            $next[] = PickupRequestStatus::IN_DEPOT->value;
        }

        return array_values(array_unique($next));
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    private function assertTransitionAllowed(PickupRequest $pickup, string $from, string $to, User $actor): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Transition from {$from} to {$to} is not allowed.",
            ]);
        }

        if ($to === PickupRequestStatus::PICKED_UP->value) {
            if (! $actor->hasPermission('pickup_requests.pickup')) {
                throw new AuthorizationException('Missing permission: pickup_requests.pickup');
            }

            if ($pickup->assigned_to !== $actor->id && ! $actor->hasPermission('pickup_requests.change_status')) {
                throw new AuthorizationException('This pickup is not assigned to you.');
            }
        }

        if ($to === PickupRequestStatus::IN_DEPOT->value && ! $actor->hasPermission('pickup_requests.change_status')) {
            throw new AuthorizationException('Missing permission: pickup_requests.change_status');
        }

        if ($to === PickupRequestStatus::CANCELLED->value
            && ! $actor->hasPermission('pickup_requests.change_status')
            && $pickup->created_by !== $actor->id) {
            throw new AuthorizationException('You cannot cancel this pickup request.');
        }
    }
}
