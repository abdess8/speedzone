<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TransferStatus;
use App\Models\Order;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function __construct(
        private readonly TransferReferenceGenerator $references,
        private readonly OrderStatusService $orderStatus,
        private readonly OrderDriverAutoAssignmentService $driverAutoAssignment,
    ) {}

    /**
     * @param  array<int, int>  $orderIds
     *
     * @throws ValidationException
     */
    public function create(
        User $actor,
        int $fromCityId,
        int $toCityId,
        array $orderIds,
        ?string $notes = null,
        ?int $assignedTo = null
    ): Transfer {
        if (! $actor->hasPermission('transfers.create')) {
            throw new AuthorizationException('Missing permission: transfers.create');
        }

        if ($fromCityId === $toCityId) {
            throw ValidationException::withMessages([
                'to_city_id' => 'Origin and destination cities must be different.',
            ]);
        }

        $orders = $this->resolveEligibleOrders($orderIds, $fromCityId, $toCityId);

        return DB::transaction(function () use ($actor, $fromCityId, $toCityId, $orders, $notes, $assignedTo): Transfer {
            $totalAmount = round((float) $orders->sum('order_amount'), 2);

            $transfer = Transfer::create([
                'reference' => $this->references->generate(),
                'from_city_id' => $fromCityId,
                'to_city_id' => $toCityId,
                'created_by' => $actor->id,
                'assigned_to' => $assignedTo,
                'status' => TransferStatus::CREATED,
                'number_of_packages' => $orders->count(),
                'total_amount' => $totalAmount,
                'notes' => $notes,
            ]);

            $transfer->recordStatus(TransferStatus::CREATED, $actor, null, 'Transfer created.');

            $this->attachOrders($transfer, $orders, OrderStatus::TRANSFER_CREATED, $actor);

            return $transfer->load([
                'fromCity',
                'toCity',
                'creator',
                'assignee',
                'orders.city',
                'orders.sector',
                'orders.seller.city',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Transfer $transfer, User $actor, array $data): Transfer
    {
        if (! $actor->hasPermission('transfers.update')) {
            throw new AuthorizationException('Missing permission: transfers.update');
        }

        $status = $transfer->status instanceof TransferStatus
            ? $transfer->status
            : TransferStatus::from($transfer->status);

        if (! $status->isEditable()) {
            throw ValidationException::withMessages([
                'transfer' => 'Transfers cannot be modified once in transit.',
            ]);
        }

        $transfer->update([
            'notes' => $data['notes'] ?? $transfer->notes,
            'assigned_to' => array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $transfer->assigned_to,
        ]);

        return $transfer->refresh()->load([
            'fromCity',
            'toCity',
            'creator',
            'assignee',
            'orders.city',
            'orders.sector',
            'orders.seller.city',
            'statusHistories.changedBy',
        ]);
    }

    public function assignStaff(Transfer $transfer, User $actor, ?int $assignedTo): Transfer
    {
        if (! $actor->hasPermission('transfers.update')) {
            throw new AuthorizationException('Missing permission: transfers.update');
        }

        $status = $transfer->status instanceof TransferStatus
            ? $transfer->status
            : TransferStatus::from($transfer->status);

        if (! $status->canAssignStaff()) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Staff cannot be assigned on a transfer in this status.',
            ]);
        }

        $transfer->update(['assigned_to' => $assignedTo]);

        return $transfer->refresh()->load([
            'fromCity',
            'toCity',
            'creator.roles',
            'assignee.roles',
            'orders.city',
            'orders.sector',
            'orders.seller.city',
            'statusHistories.changedBy',
        ]);
    }

    public function applyStatus(
        Transfer $transfer,
        TransferStatus $toStatus,
        User $actor,
        ?string $comment = null,
        ?string $fromStatus = null
    ): Transfer {
        $from = $fromStatus ?? ($transfer->status instanceof TransferStatus ? $transfer->status->value : $transfer->status);

        return DB::transaction(function () use ($transfer, $toStatus, $actor, $comment, $from): Transfer {
            $transfer->update(['status' => $toStatus->value]);
            $transfer->recordStatus($toStatus, $actor, $from, $comment);

            $orders = $transfer->orders()->get();

            if ($toStatus === TransferStatus::CANCELLED) {
                $this->releaseOrders($orders, $actor, $comment);
            } else {
                $orderStatus = $toStatus->orderStatus();
                if ($orderStatus) {
                    $this->syncOrderStatuses($orders, $orderStatus, $actor, $transfer, $comment);
                }
            }

            return $transfer->refresh()->load([
                'fromCity',
                'toCity',
                'creator',
                'assignee',
                'orders.city',
                'orders.sector',
                'orders.seller.city',
                'statusHistories.changedBy',
            ]);
        });
    }

    /**
     * Retrieve orders eligible for a transfer.
     * IN_DEPOT + pickup city (seller) = fromCity + delivery city = toCity.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Order>
     */
    public function getEligibleOrders(int $fromCityId, int $toCityId, array $filters = []): Collection
    {
        $query = Order::query()
            ->eligibleForTransfer($fromCityId, $toCityId)
            ->with(['city', 'sector', 'seller.city'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where('tracking_number', 'like', '%'.$search.'%');
        }

        if (! empty($filters['customer'])) {
            $customer = (string) $filters['customer'];
            $query->where(function (Builder $q) use ($customer): void {
                $q->where('customer_first_name', 'like', '%'.$customer.'%')
                    ->orWhere('customer_last_name', 'like', '%'.$customer.'%')
                    ->orWhereRaw("CONCAT(customer_first_name, ' ', customer_last_name) LIKE ?", ['%'.$customer.'%']);
            });
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        return $query->get();
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return Collection<int, Order>
     */
    public function resolveEligibleOrders(array $orderIds, int $fromCityId, int $toCityId): Collection
    {
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));

        if ($orderIds === []) {
            throw ValidationException::withMessages([
                'order_ids' => 'Select at least one order for the transfer.',
            ]);
        }

        $orders = Order::query()
            ->eligibleForTransfer($fromCityId, $toCityId)
            ->with(['seller.city', 'city'])
            ->whereIn('id', $orderIds)
            ->get();

        if ($orders->count() !== count($orderIds)) {
            throw ValidationException::withMessages([
                'order_ids' => 'One or more orders are invalid, not in depot, already assigned to a transfer, or do not match the selected pickup and destination cities.',
            ]);
        }

        $pickupCityIds = $orders
            ->map(fn (Order $order) => $order->seller?->city_id)
            ->filter()
            ->unique();

        if ($pickupCityIds->count() > 1 || ($pickupCityIds->first() && (int) $pickupCityIds->first() !== $fromCityId)) {
            throw ValidationException::withMessages([
                'order_ids' => 'All orders must have the same pickup city as the transfer origin.',
            ]);
        }

        $deliveryCityIds = $orders->pluck('city_id')->filter()->unique();

        if ($deliveryCityIds->count() > 1 || ($deliveryCityIds->first() && (int) $deliveryCityIds->first() !== $toCityId)) {
            throw ValidationException::withMessages([
                'order_ids' => 'All orders must have the same delivery city as the transfer destination.',
            ]);
        }

        return $orders;
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function attachOrders(
        Transfer $transfer,
        Collection $orders,
        OrderStatus $orderStatus,
        User $actor
    ): void {
        foreach ($orders as $order) {
            $transfer->transferOrders()->create([
                'order_id' => $order->id,
                'created_at' => now(),
            ]);

            $order->update(['status' => $orderStatus->value]);
            $order->recordStatus(
                $orderStatus,
                $actor,
                "Assigned to transfer {$transfer->reference}.",
                transferId: $transfer->id,
            );
        }
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function syncOrderStatuses(
        Collection $orders,
        OrderStatus $orderStatus,
        User $actor,
        Transfer $transfer,
        ?string $comment = null
    ): void {
        foreach ($orders as $order) {
            if ($order->status === $orderStatus) {
                continue;
            }

            $order->update(['status' => $orderStatus->value]);
            $order->recordStatus(
                $orderStatus,
                $actor,
                $comment ?? "Transfer {$transfer->reference} status: {$transfer->status->label()}.",
                transferId: $transfer->id,
            );

            if ($orderStatus === OrderStatus::RECEIVED_IN_DESTINATION) {
                $this->driverAutoAssignment->assignBySector($order->refresh());
            }
        }
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function releaseOrders(Collection $orders, User $actor, ?string $comment = null): void
    {
        foreach ($orders as $order) {
            $order->update(['status' => OrderStatus::IN_DEPOT->value]);
            $order->recordStatus(
                OrderStatus::IN_DEPOT,
                $actor,
                $comment ?? 'Transfer cancelled — order returned to depot.',
                pickupRequestId: $order->pickup_request_id,
            );
            $this->orderStatus->handleAutoCityDeliveryTransition($order);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function staffOptions(): Collection
    {
        return User::query()
            ->whereHas('roles.permissions', fn ($q) => $q->whereIn('name', [
                'transfers.read.assigned',
                'transfers.receive',
                'transfers.dispatch',
            ]))
            ->orderBy('first_name')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email', 'phone_number']);
    }
}
