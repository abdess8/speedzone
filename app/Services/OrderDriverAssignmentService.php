<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PartnerOrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrderDriverAssignmentService
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
        private readonly OrderAuditService $auditService,
    ) {}

    /**
     * Terminal statuses where driver assignment no longer applies.
     *
     * @var array<int, OrderStatus>
     */
    private const TERMINAL_STATUSES = [
        OrderStatus::DELIVERED,
        OrderStatus::FAILED,
        OrderStatus::REJECTED,
        OrderStatus::CANCELED,
        OrderStatus::RETURNED,
        OrderStatus::RETURN_REQUESTED,
        OrderStatus::RETURN_IN_PROGRESS,
    ];

    /**
     * Assign a driver to an order, advancing partner orders to OUT_FOR_DELIVERY when needed.
     */
    public function assign(Order $order, User $driver, User $actor, ?string $comment = null): Order
    {
        if (! $driver->isDriver()) {
            throw ValidationException::withMessages([
                'driver_id' => 'Selected user is not a driver.',
            ]);
        }

        if ($order->partner_id) {
            $this->advancePartnerOrderToOutForDelivery($order, $actor, $comment);
        } else {
            $status = $this->orderStatus($order);

            if ($status !== OrderStatus::OUT_FOR_DELIVERY) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Driver can only be assigned when the order is out for delivery.',
                ]);
            }
        }

        $previousDriver = $order->driver_id
            ? User::query()->find($order->driver_id)
            : null;

        $order->forceFill([
            'driver_id' => $driver->id,
            'assigned_at' => now(),
        ])->save();

        $this->auditService->recordDriverAssignment($order, $previousDriver, $driver, $actor);

        return $order->refresh();
    }

    /**
     * Whether the given order can receive a driver assignment right now.
     */
    public function canAssign(Order $order, User $actor): bool
    {
        if ($order->partner_id) {
            return $this->canAdvancePartnerOrderToOutForDelivery($order, $actor);
        }

        return $this->orderStatus($order) === OrderStatus::OUT_FOR_DELIVERY;
    }

    /**
     * Advance a partner order through allowed transitions until OUT_FOR_DELIVERY.
     */
    private function advancePartnerOrderToOutForDelivery(Order $order, User $actor, ?string $comment): void
    {
        if (! $this->canAdvancePartnerOrderToOutForDelivery($order, $actor)) {
            throw ValidationException::withMessages([
                'driver_id' => 'Driver cannot be assigned for the current order status.',
            ]);
        }

        $guard = 0;

        while ($this->orderStatus($order) !== OrderStatus::OUT_FOR_DELIVERY && $guard < 10) {
            $next = $this->nextPermittedTransition($order, $actor);

            if ($next === null) {
                break;
            }

            $this->transitions->transition(
                $order,
                $next,
                $actor,
                $comment ?? 'Transitioned for driver assignment.'
            );
            $order->refresh();
            $guard++;
        }

        if ($this->orderStatus($order) !== OrderStatus::OUT_FOR_DELIVERY) {
            throw ValidationException::withMessages([
                'driver_id' => 'Could not advance the order to out for delivery.',
            ]);
        }
    }

    private function canAdvancePartnerOrderToOutForDelivery(Order $order, User $actor): bool
    {
        $status = $this->orderStatus($order);

        if (in_array($status, self::TERMINAL_STATUSES, true)) {
            return false;
        }

        if ($status === OrderStatus::OUT_FOR_DELIVERY) {
            return true;
        }

        // Simulate advancing to see if OUT_FOR_DELIVERY is reachable.
        $visited = [];
        $current = $status;
        $probe = $order->replicate();

        for ($i = 0; $i < 10; $i++) {
            if ($current === OrderStatus::OUT_FOR_DELIVERY) {
                return true;
            }

            if (in_array($current, self::TERMINAL_STATUSES, true)) {
                return false;
            }

            $probe->status = $current;
            $next = $this->nextPermittedTransition($probe, $actor);

            if ($next === null) {
                return false;
            }

            $key = $current->value.'->'.$next;

            if (isset($visited[$key])) {
                return false;
            }

            $visited[$key] = true;
            $current = OrderStatus::from($next);
        }

        return $current === OrderStatus::OUT_FOR_DELIVERY;
    }

    private function nextPermittedTransition(Order $order, User $actor): ?string
    {
        foreach ($this->transitions->allowedNextStatuses($order) as $status) {
            if ($this->actorCanTransitionTo($order, $actor, $status)) {
                return $status;
            }
        }

        return null;
    }

    private function actorCanTransitionTo(Order $order, User $actor, string $status): bool
    {
        if ($order->partner_id && $actor->hasPermission('partners.deliveries.manage')) {
            return PartnerOrderStatus::isAllowed($status);
        }

        return $actor->hasPermission('orders.transition.to_'.strtolower($status));
    }

    private function orderStatus(Order $order): OrderStatus
    {
        return $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from($order->status);
    }
}
