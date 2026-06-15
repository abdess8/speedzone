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
        if (! $actor->hasPermission('returns.update_status') && ! $actor->hasPermission('returns.create')) {
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
            ReturnStatus::CREATED => 'move_to_depot',
            ReturnStatus::IN_TRANSIT_TO_DEPOT => 'receive_at_depot',
            ReturnStatus::RECEIVED_AT_DEPOT => 'send_to_seller',
            ReturnStatus::IN_TRANSIT_TO_SELLER => 'deliver_to_seller',
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

        return match ($status) {
            ReturnStatus::CREATED => $this->transitions->moveToDepot($return, $actor, $comment),
            ReturnStatus::IN_TRANSIT_TO_DEPOT => $this->transitions->transition(
                $return,
                ReturnStatus::RECEIVED_AT_DEPOT,
                $actor,
                $comment ?? 'Received at depot.',
            ),
            ReturnStatus::RECEIVED_AT_DEPOT => $this->transitions->transition(
                $return,
                ReturnStatus::IN_TRANSIT_TO_SELLER,
                $actor,
                $comment ?? 'Sent back to seller.',
            ),
            ReturnStatus::IN_TRANSIT_TO_SELLER => $this->transitions->transition(
                $return,
                ReturnStatus::DELIVERED_TO_SELLER,
                $actor,
                $comment ?? 'Delivered to seller.',
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
