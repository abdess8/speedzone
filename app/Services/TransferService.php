<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Enums\TransferContentType;
use App\Enums\TransferStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    /**
     * Relations every manifest view needs on the parcels it carries.
     *
     * `stockHubCity` travels with `seller.city` because a parcel's origin is one
     * or the other — see {@see Order::originCity()} — and loading half of that
     * pair turns a manifest of a hundred parcels into a hundred extra queries.
     *
     * @var array<int, string>
     */
    private const ORDER_RELATIONS = [
        'orders.city',
        'orders.sector',
        'orders.seller.city',
        'orders.stockHubCity',
    ];

    public function __construct(
        private readonly TransferReferenceGenerator $references,
        private readonly OrderStatusService $orderStatus,
        private readonly OrderDriverAutoAssignmentService $driverAutoAssignment,
        private readonly ReturnService $returns,
    ) {}

    /**
     * @param  array<int, int>  $orderIds
     * @param  array<int, int>  $returnIds
     *
     * @throws ValidationException
     */
    public function create(
        User $actor,
        int $fromCityId,
        int $toCityId,
        array $orderIds,
        ?string $notes = null,
        ?int $assignedTo = null,
        TransferContentType $contentType = TransferContentType::ORDERS,
        array $returnIds = [],
    ): Transfer {
        if (! $actor->hasPermission('transfers.create')) {
            throw new AuthorizationException('Missing permission: transfers.create');
        }

        if ($fromCityId === $toCityId) {
            throw ValidationException::withMessages([
                'to_city_id' => 'Origin and destination cities must be different.',
            ]);
        }

        $orders = $contentType->includesOrders()
            ? $this->resolveEligibleOrders($orderIds, $fromCityId, $toCityId, required: ! $contentType->includesReturns())
            : collect();

        $returns = $contentType->includesReturns()
            ? $this->resolveEligibleReturns($returnIds, $fromCityId, $toCityId, required: ! $contentType->includesOrders())
            : collect();

        if ($orders->isEmpty() && $returns->isEmpty()) {
            throw ValidationException::withMessages([
                'order_ids' => 'Select at least one parcel for the transfer.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $fromCityId,
            $toCityId,
            $orders,
            $returns,
            $notes,
            $assignedTo
        ): Transfer {
            $totalAmount = round((float) $orders->sum('order_amount'), 2);

            $transfer = Transfer::create([
                'reference' => $this->references->generate(),
                'from_city_id' => $fromCityId,
                'to_city_id' => $toCityId,
                'created_by' => $actor->id,
                'assigned_to' => $assignedTo,
                'status' => TransferStatus::CREATED,
                // Recomputed from what was actually selected, so a manifest
                // started as "mixed" and filled with returns only reports the
                // truth to everyone reading it downstream.
                'content_type' => TransferContentType::fromCounts($orders->count(), $returns->count()),
                'number_of_packages' => $orders->count() + $returns->count(),
                'number_of_returns' => $returns->count(),
                'total_amount' => $totalAmount,
                'notes' => $notes,
            ]);

            $transfer->recordStatus(TransferStatus::CREATED, $actor, null, 'Transfer created.');

            $this->attachOrders($transfer, $orders, OrderStatus::TRANSFER_CREATED, $actor);
            $this->attachReturns($transfer, $returns);

            return $transfer->load([
                'fromCity',
                'toCity',
                'creator',
                'assignee',
                ...self::ORDER_RELATIONS,
                'returns.order.seller.city',
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
            ...self::ORDER_RELATIONS,
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
            ...self::ORDER_RELATIONS,
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
            $returns = $transfer->returns()->with('order')->get();

            if ($toStatus === TransferStatus::CANCELLED) {
                $this->releaseOrders($orders, $actor, $comment);
                $this->releaseReturns($transfer);
            } else {
                $orderStatus = $toStatus->orderStatus();
                if ($orderStatus) {
                    $this->syncOrderStatuses($orders, $orderStatus, $actor, $transfer, $comment);
                }

                $returnStatus = $toStatus->returnStatus();
                if ($returnStatus) {
                    $this->syncReturnStatuses($returns, $returnStatus, $actor, $transfer, $comment);
                }
            }

            return $transfer->refresh()->load([
                'fromCity',
                'toCity',
                'creator',
                'assignee',
                ...self::ORDER_RELATIONS,
                'returns.order.seller.city',
                'statusHistories.changedBy',
            ]);
        });
    }

    /**
     * Parcels a manifest from this city to that one may pick up.
     *
     * Waiting in a hub with nothing left but the journey: collected and signed
     * into the depot (IN_DEPOT), or picked from stock and packed (PREPARED). The
     * origin is the depot for the second kind and the vendor's city for the
     * first — see {@see Order::scopeEligibleForTransfer()}.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Order>
     */
    public function getEligibleOrders(int $fromCityId, int $toCityId, array $filters = []): Collection
    {
        $query = Order::query()
            ->eligibleForTransfer($fromCityId, $toCityId)
            ->with(['city', 'sector', 'seller.city', 'stockHubCity'])
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
     * Returns waiting at the origin hub for a ride back to their seller's city.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, OrderReturn>
     */
    public function getEligibleReturns(int $fromCityId, int $toCityId, array $filters = []): Collection
    {
        $query = OrderReturn::query()
            ->eligibleForTransfer($fromCityId, $toCityId)
            ->with(['order.city', 'order.seller.city', 'currentLocationCity'])
            ->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('reference', 'like', '%'.$search.'%')
                    ->orWhereHas('order', fn (Builder $oq) => $oq->where('tracking_number', 'like', '%'.$search.'%'));
            });
        }

        if (! empty($filters['customer'])) {
            $customer = (string) $filters['customer'];
            $query->whereHas('order', fn (Builder $oq) => $oq->where(function (Builder $q) use ($customer): void {
                $q->where('customer_first_name', 'like', '%'.$customer.'%')
                    ->orWhere('customer_last_name', 'like', '%'.$customer.'%')
                    ->orWhereRaw("CONCAT(customer_first_name, ' ', customer_last_name) LIKE ?", ['%'.$customer.'%']);
            }));
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
     * @param  array<int, int>  $returnIds
     * @return Collection<int, OrderReturn>
     */
    public function resolveEligibleReturns(array $returnIds, int $fromCityId, int $toCityId, bool $required = true): Collection
    {
        $returnIds = array_values(array_unique(array_map('intval', $returnIds)));

        if ($returnIds === []) {
            if ($required) {
                throw ValidationException::withMessages([
                    'return_ids' => 'Select at least one return for the transfer.',
                ]);
            }

            return collect();
        }

        $returns = OrderReturn::query()
            ->eligibleForTransfer($fromCityId, $toCityId)
            ->with(['order.seller.city', 'currentLocationCity'])
            ->whereIn('id', $returnIds)
            ->get();

        if ($returns->count() !== count($returnIds)) {
            throw ValidationException::withMessages([
                'return_ids' => 'One or more returns are invalid, not waiting at the origin hub, already on a transfer, or do not belong to a seller in the destination city.',
            ]);
        }

        return $returns;
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return Collection<int, Order>
     */
    public function resolveEligibleOrders(array $orderIds, int $fromCityId, int $toCityId, bool $required = true): Collection
    {
        $orderIds = array_values(array_unique(array_map('intval', $orderIds)));

        if ($orderIds === []) {
            if ($required) {
                throw ValidationException::withMessages([
                    'order_ids' => 'Select at least one order for the transfer.',
                ]);
            }

            return collect();
        }

        $orders = Order::query()
            ->eligibleForTransfer($fromCityId, $toCityId)
            ->with(['seller.city', 'city', 'stockHubCity'])
            ->whereIn('id', $orderIds)
            ->get();

        if ($orders->count() !== count($orderIds)) {
            throw ValidationException::withMessages([
                'order_ids' => 'One or more orders are invalid, not waiting in a hub, already assigned to a transfer, or do not match the selected origin and destination cities.',
            ]);
        }

        // The origin is the depot for a stock order and the vendor's city for a
        // collected parcel, so the two flows are compared on the same footing.
        $originCityIds = $orders
            ->map(fn (Order $order) => $order->originCityId())
            ->filter()
            ->unique();

        if ($originCityIds->count() > 1 || ($originCityIds->first() && (int) $originCityIds->first() !== $fromCityId)) {
            throw ValidationException::withMessages([
                'order_ids' => 'All orders must start from the same city as the transfer origin.',
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
     * Returns keep their RECEIVED_AT_HUB status while the manifest is filled;
     * they only move once the truck leaves.
     *
     * @param  Collection<int, OrderReturn>  $returns
     */
    private function attachReturns(Transfer $transfer, Collection $returns): void
    {
        foreach ($returns as $return) {
            $transfer->transferReturns()->create([
                'return_id' => $return->id,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Walk the manifest's returns to the status matching the transfer leg.
     *
     * Dispatch puts them on the road, receipt lands them at the seller's hub.
     * The statuses are applied directly rather than through the return
     * transition service: authorising the transfer leg is what authorises this,
     * and the driver who signs a manifest in holds `transfers.receive` without
     * necessarily holding the per-step return grants. Order statuses on the
     * same manifest are synced the same way.
     *
     * @param  Collection<int, OrderReturn>  $returns
     */
    private function syncReturnStatuses(
        Collection $returns,
        ReturnStatus $toStatus,
        User $actor,
        Transfer $transfer,
        ?string $comment = null
    ): void {
        foreach ($returns as $return) {
            if ($return->status === $toStatus) {
                continue;
            }

            $this->returns->applyStatus(
                $return,
                $toStatus,
                $actor,
                $comment ?? "Transfer {$transfer->reference}: {$toStatus->label()}.",
                locationCityId: $toStatus === ReturnStatus::ARRIVED_VENDOR_HUB ? $transfer->to_city_id : null,
            );
        }
    }

    /**
     * Dropping the pivot rows is what puts the parcels back in the pool. Their
     * own status is untouched: a manifest can only be cancelled before it is
     * dispatched, so the returns never left RECEIVED_AT_HUB.
     */
    private function releaseReturns(Transfer $transfer): void
    {
        $transfer->transferReturns()->delete();
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
     * Put the parcels of a cancelled manifest back on the shelf they came from.
     *
     * A collected parcel goes back to IN_DEPOT; one picked from stock goes back
     * to PREPARED, because it never was in the depot in that sense — it is a
     * packed box waiting for a ride, and sending it to IN_DEPOT would offer it
     * to a pickup flow it does not belong to.
     *
     * @param  Collection<int, Order>  $orders
     */
    private function releaseOrders(Collection $orders, User $actor, ?string $comment = null): void
    {
        foreach ($orders as $order) {
            $shelf = $order->stock_hub_city_id ? OrderStatus::PREPARED : OrderStatus::IN_DEPOT;

            $order->update(['status' => $shelf->value]);
            $order->recordStatus(
                $shelf,
                $actor,
                $comment ?? 'Transfer cancelled — order returned to depot.',
                pickupRequestId: $order->pickup_request_id,
            );

            if ($shelf === OrderStatus::PREPARED) {
                $this->orderStatus->handlePreparedRouting($order);

                continue;
            }

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
