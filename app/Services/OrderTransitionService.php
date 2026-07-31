<?php

namespace App\Services;

use App\Enums\OrderFailureReason;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Partners\PartnerApiException;
use App\Services\Partners\PartnerOutboundSyncService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderTransitionService
{
    public function __construct(
        private readonly OrderStatusService $orderStatus,
        private readonly DriverPaymentService $driverPayment,
        private readonly PartnerOutboundSyncService $outboundSync,
        private readonly OrderDriverAutoAssignmentService $driverAutoAssignment,
    ) {}

    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        OrderStatus::CREATED->value => [
            OrderStatus::PICKUP_REQUESTED->value,
            OrderStatus::REJECTED->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::PICKUP_REQUESTED->value => [
            OrderStatus::WAITING_PICKUP->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::WAITING_PICKUP->value => [
            OrderStatus::PICKED_UP->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::PICKED_UP->value => [
            OrderStatus::IN_DEPOT->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::IN_DEPOT->value => [
            OrderStatus::TRANSFER_CREATED->value,
            OrderStatus::IN_DELIVERY_CITY->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::TRANSFER_CREATED->value => [
            OrderStatus::IN_TRANSIT->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::IN_TRANSIT->value => [
            OrderStatus::RECEIVED_IN_DESTINATION->value,
            OrderStatus::REJECTED->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::RECEIVED_IN_DESTINATION->value => [
            OrderStatus::OUT_FOR_DELIVERY->value,
            OrderStatus::REJECTED->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::IN_DELIVERY_CITY->value => [
            OrderStatus::OUT_FOR_DELIVERY->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::OUT_FOR_DELIVERY->value => [
            OrderStatus::DELIVERED->value,
            OrderStatus::FAILED->value,
            OrderStatus::REJECTED->value,
            OrderStatus::CANCELED->value,
        ],
        OrderStatus::DELIVERED->value => [],
        OrderStatus::FAILED->value => [],
        OrderStatus::REJECTED->value => [],
        OrderStatus::CANCELED->value => [],
        OrderStatus::RETURN_REQUESTED->value => [],
        OrderStatus::RETURN_IN_PROGRESS->value => [],
        OrderStatus::RETURNED->value => [],
    ];

    /**
     * @param  array{failure_reason?: string|null, failure_note?: string|null}  $context
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function transition(
        Order $order,
        string $toStatus,
        User $actor,
        ?string $comment = null,
        array $context = [],
    ): Order {
        $this->assertTransitionAllowed($order, $toStatus, $actor);
        $this->syncWithPartner($order, $toStatus, $comment);

        return $this->transitionWithoutPartnerSync($order, $toStatus, $actor, $comment, $context);
    }

    /**
     * Apply a validated status transition without triggering outbound partner sync.
     *
     * @param  array{failure_reason?: string|null, failure_note?: string|null}  $context
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function transitionWithoutPartnerSync(
        Order $order,
        string $toStatus,
        User $actor,
        ?string $comment = null,
        array $context = [],
    ): Order {
        $this->assertTransitionAllowed($order, $toStatus, $actor);

        return DB::transaction(function () use ($order, $toStatus, $actor, $comment, $context): Order {
            $attributes = ['status' => $toStatus];

            // Stamp the delivery time so the driver payout is dated correctly.
            if ($toStatus === OrderStatus::DELIVERED->value) {
                $attributes['delivered_at'] = now();
            }

            // A non-delivered order must always carry a reason so the seller and
            // the return workflow can tell a refusal from an unreachable customer.
            if ($toStatus === OrderStatus::FAILED->value) {
                $attributes['failure_reason'] = OrderFailureReason::tryFrom(
                    (string) ($context['failure_reason'] ?? '')
                ) ?? OrderFailureReason::OTHER;
                $attributes['failure_note'] = $context['failure_note'] ?? null;
                $attributes['failed_at'] = now();
            }

            $order->update($attributes);
            $order->recordStatus(
                $toStatus,
                $actor,
                $this->historyComment($comment, $attributes['failure_reason'] ?? null, $attributes['failure_note'] ?? null)
            );

            if ($toStatus === OrderStatus::IN_DEPOT->value) {
                $this->orderStatus->handleAutoCityDeliveryTransition($order);
            }

            if ($toStatus === OrderStatus::RECEIVED_IN_DESTINATION->value) {
                $this->driverAutoAssignment->assignBySector($order->refresh());
            }

            // A delivered order earns the assigned driver the sector driver price.
            if ($toStatus === OrderStatus::DELIVERED->value) {
                $this->driverPayment->recordDeliveryPayment($order->refresh(), $actor);
            }

            $this->outboundSync->clearSyncError($order);

            return $order->refresh();
        });
    }

    /**
     * Surface the failure reason in the tracking timeline, which only renders
     * the history comment.
     */
    private function historyComment(?string $comment, ?OrderFailureReason $reason, ?string $note): ?string
    {
        if (! $reason) {
            return $comment;
        }

        return collect([$reason->label(), $note, $comment])
            ->filter(fn (?string $part) => filled($part))
            ->implode(' — ');
    }

    /**
     * The whole transition graph, keyed by source status.
     *
     * Exposed so a list screen can resolve "what can I do with this row?" for a
     * full page of orders without instantiating the service per row.
     *
     * @return array<string, array<int, string>>
     */
    public static function transitionMap(): array
    {
        return self::ALLOWED_TRANSITIONS;
    }

    /**
     * Statuses an order may legally transition to from its current status.
     *
     * @return array<int, string>
     */
    public function allowedNextStatuses(Order $order): array
    {
        $current = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        return self::ALLOWED_TRANSITIONS[$current] ?? [];
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    private function assertTransitionAllowed(Order $order, string $toStatus, User $actor): void
    {
        $fromStatus = $order->status instanceof OrderStatus ? $order->status->value : $order->status;
        $allowedNextStatuses = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];

        if (! in_array($toStatus, $allowedNextStatuses, true)) {
            throw ValidationException::withMessages([
                'to_status' => "Transition from {$fromStatus} to {$toStatus} is not allowed.",
            ]);
        }

        $permission = 'orders.transition.to_'.strtolower($toStatus);

        if (! $actor->hasPermission($permission)) {
            throw new AuthorizationException("Missing required permission: {$permission}");
        }
    }

    /**
     * @throws ValidationException
     */
    private function syncWithPartner(Order $order, string $targetStatus, ?string $comment): void
    {
        if ($order->suppressPartnerStatusSync || ! $this->outboundSync->shouldSync($order)) {
            return;
        }

        $order->loadMissing('partner');

        try {
            $this->outboundSync->pushStatusChange($order, $targetStatus, $comment);
        } catch (PartnerApiException $e) {
            $this->outboundSync->recordFailure($order, $e);
        }
    }
}
