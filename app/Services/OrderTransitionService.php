<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderTransitionService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        OrderStatus::CREATED->value => [OrderStatus::PICKUP_REQUESTED->value],
        OrderStatus::PICKUP_REQUESTED->value => [OrderStatus::WAITING_PICKUP->value],
        OrderStatus::WAITING_PICKUP->value => [OrderStatus::PICKED_UP->value],
        OrderStatus::PICKED_UP->value => [OrderStatus::IN_DEPOT->value],
        OrderStatus::IN_DEPOT->value => [OrderStatus::TRANSFER_CREATED->value],
        OrderStatus::TRANSFER_CREATED->value => [OrderStatus::IN_TRANSIT->value],
        OrderStatus::IN_TRANSIT->value => [OrderStatus::RECEIVED_IN_DESTINATION->value],
        OrderStatus::RECEIVED_IN_DESTINATION->value => [OrderStatus::OUT_FOR_DELIVERY->value],
        OrderStatus::IN_DELIVERY_CITY->value => [OrderStatus::OUT_FOR_DELIVERY->value],
        OrderStatus::OUT_FOR_DELIVERY->value => [
            OrderStatus::DELIVERED->value,
            OrderStatus::FAILED->value,
            OrderStatus::RETURNED->value,
        ],
        OrderStatus::DELIVERED->value => [],
        OrderStatus::FAILED->value => [OrderStatus::RETURNED->value],
        OrderStatus::RETURNED->value => [],
    ];

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function transition(Order $order, string $toStatus, User $actor, ?string $comment = null): Order
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

        return DB::transaction(function () use ($order, $toStatus, $actor, $comment): Order {
            $order->update(['status' => $toStatus]);
            $order->recordStatus($toStatus, $actor, $comment);

            return $order->refresh();
        });
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
}
