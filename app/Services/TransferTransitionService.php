<?php

namespace App\Services;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class TransferTransitionService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        TransferStatus::CREATED->value => [
            TransferStatus::WAITING_DISPATCH->value,
            TransferStatus::CANCELLED->value,
        ],
        TransferStatus::WAITING_DISPATCH->value => [
            TransferStatus::IN_TRANSIT->value,
            TransferStatus::CANCELLED->value,
        ],
        TransferStatus::IN_TRANSIT->value => [
            TransferStatus::RECEIVED->value,
        ],
        TransferStatus::RECEIVED->value => [],
        TransferStatus::CANCELLED->value => [],
    ];

    public function __construct(private readonly TransferService $transfers) {}

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function transition(
        Transfer $transfer,
        TransferStatus|string $toStatus,
        User $actor,
        ?string $comment = null
    ): Transfer {
        $to = $toStatus instanceof TransferStatus ? $toStatus->value : $toStatus;
        $from = $transfer->status instanceof TransferStatus ? $transfer->status->value : $transfer->status;

        $this->assertTransitionAllowed($transfer, $from, $to, $actor);

        return $this->transfers->applyStatus($transfer, TransferStatus::from($to), $actor, $comment, $from);
    }

    /**
     * Advance dispatch flow: CREATED → WAITING_DISPATCH, or WAITING_DISPATCH → IN_TRANSIT.
     */
    public function dispatch(Transfer $transfer, User $actor, ?string $comment = null): Transfer
    {
        if (! $actor->hasPermission('transfers.dispatch')) {
            throw new AuthorizationException('Missing permission: transfers.dispatch');
        }

        $current = $transfer->status instanceof TransferStatus ? $transfer->status : TransferStatus::from($transfer->status);

        $next = match ($current) {
            TransferStatus::CREATED => TransferStatus::WAITING_DISPATCH,
            TransferStatus::WAITING_DISPATCH => TransferStatus::IN_TRANSIT,
            default => throw ValidationException::withMessages([
                'transfer' => 'This transfer cannot be dispatched from its current status.',
            ]),
        };

        return $this->transition($transfer, $next, $actor, $comment);
    }

    /**
     * Mark transfer as received at destination: IN_TRANSIT → RECEIVED.
     */
    public function receive(Transfer $transfer, User $actor, ?string $comment = null): Transfer
    {
        if (! $actor->hasPermission('transfers.receive')) {
            throw new AuthorizationException('Missing permission: transfers.receive');
        }

        return $this->transition($transfer, TransferStatus::RECEIVED, $actor, $comment);
    }

    /**
     * @return array<int, string>
     */
    public function allowedNextStatuses(Transfer $transfer, User $actor): array
    {
        $current = $transfer->status instanceof TransferStatus ? $transfer->status->value : $transfer->status;
        $next = [];

        if ($current === TransferStatus::CREATED->value && $actor->hasPermission('transfers.dispatch')) {
            $next[] = TransferStatus::WAITING_DISPATCH->value;
        }

        if ($current === TransferStatus::WAITING_DISPATCH->value && $actor->hasPermission('transfers.dispatch')) {
            $next[] = TransferStatus::IN_TRANSIT->value;
        }

        if ($current === TransferStatus::IN_TRANSIT->value && $actor->hasPermission('transfers.receive')) {
            $next[] = TransferStatus::RECEIVED->value;
        }

        if (in_array($current, [TransferStatus::CREATED->value, TransferStatus::WAITING_DISPATCH->value], true)
            && $actor->hasPermission('transfers.update')) {
            $next[] = TransferStatus::CANCELLED->value;
        }

        return array_values(array_unique($next));
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    private function assertTransitionAllowed(Transfer $transfer, string $from, string $to, User $actor): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Transition from {$from} to {$to} is not allowed.",
            ]);
        }

        if (in_array($to, [TransferStatus::WAITING_DISPATCH->value, TransferStatus::IN_TRANSIT->value], true)
            && ! $actor->hasPermission('transfers.dispatch')) {
            throw new AuthorizationException('Missing permission: transfers.dispatch');
        }

        if ($to === TransferStatus::RECEIVED->value && ! $actor->hasPermission('transfers.receive')) {
            throw new AuthorizationException('Missing permission: transfers.receive');
        }

        if ($to === TransferStatus::CANCELLED->value && ! $actor->hasPermission('transfers.update')) {
            throw new AuthorizationException('Missing permission: transfers.update');
        }
    }
}
