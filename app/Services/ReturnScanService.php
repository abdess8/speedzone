<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ReturnScanService
{
    public function __construct(
        private readonly ReturnTransitionService $transitions,
    ) {}

    /**
     * Validate a scanned return reference or order tracking number.
     *
     * @return array<string, mixed>
     */
    public function validateScan(User $actor, string $input): array
    {
        if (! $actor->canUpdateReturnStatus() && ! $actor->hasPermission('returns.create')) {
            throw new AuthorizationException('Missing permission to scan returns.');
        }

        $reference = $this->extractReference($input);
        $return = OrderReturn::query()
            ->with(['order.city', 'order.seller.city', 'currentLocationCity'])
            ->where('reference', strtoupper($reference))
            ->first();

        if (! $return) {
            $order = $this->resolveOrderFromInput($input);

            if ($order && $order->activeReturn()) {
                $return = $order->activeReturn()->load(['order.city', 'order.seller.city', 'currentLocationCity']);
            }
        }

        if (! $return) {
            throw ValidationException::withMessages([
                'scan' => 'Return not found for this scan.',
            ]);
        }

        if ($return->isTerminal()) {
            throw ValidationException::withMessages([
                'scan' => 'This return is already completed or cancelled.',
            ]);
        }

        $status = $return->status instanceof ReturnStatus ? $return->status : ReturnStatus::from($return->status);

        $nextAction = match ($status) {
            ReturnStatus::CREATED => 'receive_at_hub',
            ReturnStatus::IN_TRANSIT_TO_DEPOT => 'arrive_vendor_hub',
            ReturnStatus::ARRIVED_VENDOR_HUB => 'start_delivery_to_vendor',
            ReturnStatus::IN_DELIVERY_TO_VENDOR => 'deliver_to_vendor',
            default => null,
        };

        return [
            'valid' => true,
            'return' => [
                'id' => $return->id,
                'reference' => $return->reference,
                'status' => $status->value,
                'status_label' => $status->label(),
                'next_action' => $nextAction,
                'order_tracking' => $return->order?->tracking_number,
            ],
        ];
    }

    /**
     * Process scan action based on current return status.
     */
    public function processScan(User $actor, string $input, ?string $comment = null): OrderReturn
    {
        $validation = $this->validateScan($actor, $input);
        $return = OrderReturn::query()->findOrFail($validation['return']['id']);
        $status = $return->status instanceof ReturnStatus ? $return->status : ReturnStatus::from($return->status);

        // RECEIVED_AT_HUB has no scan action on purpose: the parcel leaves the
        // hub inside a transfer manifest, and dispatching that manifest is what
        // moves the return on.
        return match ($status) {
            ReturnStatus::CREATED => $this->transitions->receiveAtHub($return, $actor, $comment),
            ReturnStatus::IN_TRANSIT_TO_DEPOT => $this->transitions->transition(
                $return,
                ReturnStatus::ARRIVED_VENDOR_HUB,
                $actor,
                $comment ?? 'Received at the vendor hub.',
            ),
            ReturnStatus::ARRIVED_VENDOR_HUB => $this->transitions->transition(
                $return,
                ReturnStatus::IN_DELIVERY_TO_VENDOR,
                $actor,
                $comment ?? 'Out for hand-back to the vendor.',
            ),
            ReturnStatus::IN_DELIVERY_TO_VENDOR => $this->transitions->transition(
                $return,
                ReturnStatus::DELIVERED_TO_VENDOR,
                $actor,
                $comment ?? 'Handed back to the vendor.',
            ),
            default => throw ValidationException::withMessages([
                'scan' => 'No scan action available for the current return status.',
            ]),
        };
    }

    private function extractReference(string $input): string
    {
        $input = trim($input);

        if (preg_match('#/returns/([A-Za-z0-9-]+)#', $input, $matches)) {
            return strtoupper($matches[1]);
        }

        return strtoupper($input);
    }

    private function resolveOrderFromInput(string $input): ?Order
    {
        $input = trim($input);

        if (preg_match('#/orders/([A-Za-z0-9-]+)#', $input, $matches)) {
            return Order::query()->where('tracking_number', strtoupper($matches[1]))->first();
        }

        return Order::query()->where('tracking_number', strtoupper($input))->first();
    }
}
