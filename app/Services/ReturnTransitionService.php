<?php

namespace App\Services;

use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ReturnTransitionService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        ReturnStatus::CREATED->value => [
            ReturnStatus::IN_TRANSIT_TO_DEPOT->value,
            ReturnStatus::CANCELLED->value,
        ],
        ReturnStatus::IN_TRANSIT_TO_DEPOT->value => [
            ReturnStatus::RECEIVED_AT_DEPOT->value,
        ],
        ReturnStatus::RECEIVED_AT_DEPOT->value => [
            ReturnStatus::IN_TRANSIT_TO_SELLER->value,
        ],
        ReturnStatus::IN_TRANSIT_TO_SELLER->value => [
            ReturnStatus::DELIVERED_TO_SELLER->value,
        ],
        ReturnStatus::DELIVERED_TO_SELLER->value => [],
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
     * Driver shortcut: CREATED → IN_TRANSIT_TO_DEPOT.
     */
    public function moveToDepot(OrderReturn $return, User $actor, ?string $comment = null, ?int $cityId = null): OrderReturn
    {
        if (! $actor->hasPermission('returns.update_status')) {
            throw new AuthorizationException('Missing permission: returns.update_status');
        }

        return $this->transition(
            $return,
            ReturnStatus::IN_TRANSIT_TO_DEPOT,
            $actor,
            $comment ?? 'Picked up by driver — in transit to depot.',
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
            if ($this->canTransitionTo($return, $status, $actor)) {
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

        if (! $this->canTransitionTo($return, $to, $actor)) {
            throw new AuthorizationException('You are not allowed to perform this return status transition.');
        }
    }

    private function canTransitionTo(OrderReturn $return, string $to, User $actor): bool
    {
        if ($actor->hasPermission('returns.manage')) {
            return true;
        }

        if ($to === ReturnStatus::CANCELLED->value) {
            return $actor->hasPermission('returns.manage');
        }

        if ($to === ReturnStatus::IN_TRANSIT_TO_DEPOT->value) {
            return $actor->hasPermission('returns.update_status')
                || $actor->hasPermission('returns.create');
        }

        return $actor->hasPermission('returns.update_status');
    }
}
