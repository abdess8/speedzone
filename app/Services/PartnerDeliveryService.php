<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PartnerOrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Partners\PartnerApiException;
use App\Services\Partners\PartnerOutboundSyncService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerDeliveryService
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
        private readonly PartnerOutboundSyncService $outboundSync,
    ) {}

    /**
     * Update a partner delivery status after validating the allowed vocabulary.
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function updateStatus(Order $order, string $status, User $actor, ?string $comment = null): Order
    {
        if (! $order->partner_id) {
            throw ValidationException::withMessages([
                'order' => 'Only partner deliveries can be updated through this endpoint.',
            ]);
        }

        if (! PartnerOrderStatus::isAllowed($status)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid partner delivery status. Allowed: '.implode(', ', PartnerOrderStatus::values()),
            ]);
        }

        $toStatus = OrderStatus::from($status)->value;
        $fromStatus = $order->status instanceof OrderStatus
            ? $order->status->value
            : (string) $order->status;

        if ($fromStatus === $toStatus) {
            return $order;
        }

        $this->syncWithPartner($order, $toStatus, $comment);

        return DB::transaction(function () use ($order, $toStatus, $actor, $comment, $fromStatus): Order {
            $allowedNext = $this->transitions->allowedNextStatuses($order);

            if (in_array($toStatus, $allowedNext, true)) {
                return $this->applyLocalStatusChange(
                    $this->transitions->transitionWithoutPartnerSync($order, $toStatus, $actor, $comment),
                );
            }

            // Partner deliveries may jump between the five allowed operational statuses.
            if (! PartnerOrderStatus::isAllowed($fromStatus)) {
                throw ValidationException::withMessages([
                    'status' => "Transition from {$fromStatus} to {$toStatus} is not allowed.",
                ]);
            }

            $attributes = ['status' => $toStatus];

            if ($toStatus === OrderStatus::DELIVERED->value) {
                $attributes['delivered_at'] = now();
            }

            $order->update($attributes);
            $order->recordStatus($toStatus, $actor, $comment ?? 'Partner delivery status updated.');

            if ($toStatus === OrderStatus::DELIVERED->value) {
                app(DriverPaymentService::class)->recordDeliveryPayment($order->refresh(), $actor);
            }

            return $this->applyLocalStatusChange($order->refresh());
        });
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

    private function applyLocalStatusChange(Order $order): Order
    {
        $this->outboundSync->clearSyncError($order);

        return $order->refresh();
    }
}
