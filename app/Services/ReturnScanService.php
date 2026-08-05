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

        return [
            'valid' => true,
            'return' => [
                'id' => $return->id,
                'reference' => $return->reference,
                'status' => $status->value,
                'status_label' => $status->label(),
                'next_action' => $this->nextAction($return, $status),
                'order_tracking' => $return->order?->tracking_number,
                'seller_name' => $return->order?->seller?->full_name,
                'city_id' => $return->handBackCityId(),
                'city_name' => $return->currentLocationCity?->name,
                'assigned_to' => $return->assigned_to,
            ],
        ];
    }

    /**
     * The step a scan of this parcel would take, or null when the parcel is
     * waiting on something a scan cannot provide (a transfer manifest).
     */
    private function nextAction(OrderReturn $return, ReturnStatus $status): ?string
    {
        return match ($status) {
            ReturnStatus::CREATED => 'receive_at_hub',
            // Skipping the transfer is only offered when there is nothing to
            // transfer: the parcel failed in the seller's own city.
            ReturnStatus::RECEIVED_AT_HUB => $return->isAtVendorCity() ? 'arrive_vendor_hub' : null,
            ReturnStatus::IN_TRANSIT_TO_DEPOT => 'arrive_vendor_hub',
            ReturnStatus::ARRIVED_VENDOR_HUB => 'start_delivery_to_vendor',
            ReturnStatus::IN_DELIVERY_TO_VENDOR => 'deliver_to_vendor',
            default => null,
        };
    }

    /**
     * Process scan action based on current return status.
     */
    public function processScan(User $actor, string $input, ?string $comment = null, ?int $driverId = null): OrderReturn
    {
        $validation = $this->validateScan($actor, $input);
        $return = OrderReturn::query()->with('order.seller')->findOrFail($validation['return']['id']);
        $status = $return->status instanceof ReturnStatus ? $return->status : ReturnStatus::from($return->status);

        // A parcel still waiting for a transfer manifest has no scan action:
        // dispatching that manifest is what moves the return on.
        return match ($this->nextAction($return, $status)) {
            'receive_at_hub' => $this->transitions->receiveAtHub($return, $actor, $comment),
            'arrive_vendor_hub' => $this->transitions->transition(
                $return,
                ReturnStatus::ARRIVED_VENDOR_HUB,
                $actor,
                $comment ?? 'Received at the vendor hub.',
            ),
            'start_delivery_to_vendor' => $this->transitions->handBack(
                $return,
                $actor,
                $this->resolveHandBackDriver($return, $actor, $driverId),
                $comment,
            ),
            'deliver_to_vendor' => $this->transitions->transition(
                $return,
                ReturnStatus::DELIVERED_TO_VENDOR,
                $actor,
                $comment ?? 'Handed back to the vendor.',
            ),
            default => throw ValidationException::withMessages([
                'scan' => __('returns.errors.no_scan_action'),
            ]),
        };
    }

    /**
     * Who is taking the parcel out.
     *
     * An explicit choice wins; otherwise a driver scanning at the vendor hub is
     * taken to be loading it himself, which is the whole point of him standing
     * there with a phone.
     */
    private function resolveHandBackDriver(OrderReturn $return, User $actor, ?int $driverId): User
    {
        if ($driverId) {
            return User::query()->findOrFail($driverId);
        }

        if ($return->assigned_to) {
            return User::query()->findOrFail($return->assigned_to);
        }

        if ($actor->isDriver()) {
            return $actor;
        }

        throw ValidationException::withMessages([
            'driver_id' => __('returns.errors.driver_required'),
        ]);
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
