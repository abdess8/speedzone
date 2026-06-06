<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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
        OrderStatus::IN_DEPOT->value => [OrderStatus::IN_TRANSIT->value],
        OrderStatus::IN_TRANSIT->value => [OrderStatus::IN_DELIVERY_CITY->value],
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
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function transition(Order $order, string $toStatus, User $actor): Order
    {
        $fromStatus = $order->status;
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

        $order->update(['status' => $toStatus]);

        return $order->refresh();
    }
}
