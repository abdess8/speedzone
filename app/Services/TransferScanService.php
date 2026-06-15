<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TransferStatus;
use App\Models\Order;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferScanService
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly TransferTransitionService $transitions,
    ) {}

    /**
     * Validate a scanned order tracking number against a transfer context.
     *
     * @return array<string, mixed>
     */
    public function validateOrderScan(User $actor, Transfer $transfer, string $trackingNumber): array
    {
        $this->assertCanScan($actor, $transfer);

        $order = $this->resolveOrder($trackingNumber);

        if (! $transfer->orders()->where('orders.id', $order->id)->exists()) {
            return [
                'valid' => false,
                'message' => 'This order does not belong to this transfer.',
                'order' => null,
            ];
        }

        $expectedStatus = $this->expectedOrderStatus($transfer);

        if ($order->status !== $expectedStatus) {
            return [
                'valid' => false,
                'message' => "Order status must be {$expectedStatus->label()}.",
                'order' => $this->orderPayload($order),
            ];
        }

        return [
            'valid' => true,
            'message' => 'Order validated successfully.',
            'order' => $this->orderPayload($order),
        ];
    }

    /**
     * Bulk receive orders via scanning and complete the transfer when all are validated.
     *
     * @param  array<int, string>  $trackingNumbers
     * @return array{updated: int, transfer_completed: bool, orders: Collection<int, Order>}
     */
    public function bulkReceive(User $actor, Transfer $transfer, array $trackingNumbers): array
    {
        if (! $actor->hasPermission('transfers.receive')) {
            throw new AuthorizationException('Missing permission: transfers.receive');
        }

        $status = $transfer->status instanceof TransferStatus
            ? $transfer->status
            : TransferStatus::from($transfer->status);

        if ($status !== TransferStatus::IN_TRANSIT) {
            throw ValidationException::withMessages([
                'transfer' => 'Only in-transit transfers can be received via scan.',
            ]);
        }

        $trackingNumbers = array_values(array_unique(array_filter(array_map('trim', $trackingNumbers))));

        if ($trackingNumbers === []) {
            throw ValidationException::withMessages([
                'orders' => 'Scan at least one order.',
            ]);
        }

        return DB::transaction(function () use ($actor, $transfer, $trackingNumbers): array {
            $updated = 0;
            $orders = collect();

            foreach ($trackingNumbers as $trackingNumber) {
                $result = $this->validateOrderScan($actor, $transfer, $trackingNumber);

                if (! $result['valid']) {
                    throw ValidationException::withMessages([
                        'orders' => $result['message'],
                    ]);
                }

                /** @var Order $order */
                $order = Order::query()->where('tracking_number', $this->parseTrackingNumber($trackingNumber))->first();

                $order->update(['status' => OrderStatus::RECEIVED_IN_DESTINATION->value]);
                $order->recordStatus(
                    OrderStatus::RECEIVED_IN_DESTINATION,
                    $actor,
                    "Received via scan on transfer {$transfer->reference}."
                );

                $updated++;
                $orders->push($order->refresh());
            }

            $transferCompleted = false;
            $remaining = $transfer->orders()
                ->where('status', '!=', OrderStatus::RECEIVED_IN_DESTINATION->value)
                ->count();

            if ($remaining === 0) {
                $this->transitions->receive($transfer, $actor, 'All orders received via bulk scan.');
                $transferCompleted = true;
            }

            return [
                'updated' => $updated,
                'transfer_completed' => $transferCompleted,
                'orders' => $orders,
            ];
        });
    }

    /**
     * Resolve transfer from a scanned QR URL or reference.
     */
    public function resolveTransferFromScan(string $input): ?Transfer
    {
        $reference = $this->parseTransferReference($input);

        if (! $reference) {
            return null;
        }

        return Transfer::query()->where('reference', $reference)->first();
    }

    public function parseTransferReference(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('#/transfers/([A-Za-z0-9-]+)#', $input, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^TRF-\d{4}-\d+$/i', $input)) {
            return strtoupper($input);
        }

        return null;
    }

    private function parseTrackingNumber(string $input): string
    {
        $input = trim($input);

        if (preg_match('#/orders/([A-Za-z0-9-]+)#', $input, $matches)) {
            return $matches[1];
        }

        return $input;
    }

    private function resolveOrder(string $trackingNumber): Order
    {
        $tracking = $this->parseTrackingNumber($trackingNumber);

        $order = Order::query()->where('tracking_number', $tracking)->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'tracking_number' => 'Order not found.',
            ]);
        }

        return $order;
    }

    private function expectedOrderStatus(Transfer $transfer): OrderStatus
    {
        $status = $transfer->status instanceof TransferStatus
            ? $transfer->status
            : TransferStatus::from($transfer->status);

        return match ($status) {
            TransferStatus::IN_TRANSIT => OrderStatus::IN_TRANSIT,
            TransferStatus::RECEIVED => OrderStatus::RECEIVED_IN_DESTINATION,
            default => OrderStatus::TRANSFER_CREATED,
        };
    }

    private function assertCanScan(User $actor, Transfer $transfer): void
    {
        if (! $actor->hasPermission('transfers.receive') && ! $actor->hasPermission('transfers.dispatch')) {
            throw new AuthorizationException('Missing permission to scan transfer orders.');
        }

        if ($actor->hasPermission('transfers.read.assigned')
            && ! $actor->hasPermission('transfers.read')
            && $transfer->assigned_to !== $actor->id) {
            throw new AuthorizationException('This transfer is not assigned to you.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'status' => $order->status instanceof OrderStatus ? $order->status->value : $order->status,
            'status_label' => $order->status instanceof OrderStatus ? $order->status->label() : $order->status,
        ];
    }
}
