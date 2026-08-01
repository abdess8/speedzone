<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Events\ReturnRequested;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function __construct(private readonly ReturnReferenceGenerator $references) {}

    /**
     * Order statuses eligible for a new return request.
     *
     * @return array<int, string>
     */
    public static function eligibleOrderStatuses(ReturnInitiatedByRole $role): array
    {
        return match ($role) {
            ReturnInitiatedByRole::DRIVER => [
                OrderStatus::OUT_FOR_DELIVERY->value,
                OrderStatus::FAILED->value,
            ],
            ReturnInitiatedByRole::SELLER => [
                OrderStatus::IN_TRANSIT->value,
                OrderStatus::RECEIVED_IN_DESTINATION->value,
                OrderStatus::IN_DELIVERY_CITY->value,
                OrderStatus::OUT_FOR_DELIVERY->value,
                OrderStatus::DELIVERED->value,
            ],
            ReturnInitiatedByRole::ADMIN, ReturnInitiatedByRole::SYSTEM => [
                OrderStatus::OUT_FOR_DELIVERY->value,
                OrderStatus::FAILED->value,
                OrderStatus::DELIVERED->value,
                OrderStatus::IN_TRANSIT->value,
                OrderStatus::RECEIVED_IN_DESTINATION->value,
                OrderStatus::IN_DELIVERY_CITY->value,
            ],
        };
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function create(
        Order $order,
        User $actor,
        ReturnInitiatedByRole $role,
        string $reason,
        ?string $notes = null,
        ?int $currentCityId = null,
    ): OrderReturn {
        $this->assertCanCreate($actor, $role, $order);

        if ($order->activeReturn()) {
            throw ValidationException::withMessages([
                'order_id' => 'This order already has an active return.',
            ]);
        }

        $existingReturn = $order->orderReturn;
        if ($existingReturn && $existingReturn->status !== ReturnStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'order_id' => 'This order already has a return on record.',
            ]);
        }

        $eligible = self::eligibleOrderStatuses($role);
        $orderStatus = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        if (! in_array($orderStatus, $eligible, true)) {
            throw ValidationException::withMessages([
                'order_id' => 'This order is not eligible for a return in its current status.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $role, $reason, $notes, $currentCityId): OrderReturn {
            $cityId = $currentCityId ?? $order->city_id;

            $return = OrderReturn::create([
                'reference' => $this->references->generate(),
                'order_id' => $order->id,
                'created_by' => $actor->id,
                'initiated_by_role' => $role,
                'reason' => $reason,
                'status' => ReturnStatus::CREATED,
                'current_location_city_id' => $cityId,
                'return_address' => $order->customer_address,
                'return_notes' => $notes,
            ]);

            $return->recordStatus(ReturnStatus::CREATED, $actor, null, 'Return created.');

            $order->update([
                'return_id' => $return->id,
                'is_returned' => false,
                'status' => OrderStatus::RETURN_REQUESTED->value,
            ]);

            $order->recordStatus(
                OrderStatus::RETURN_REQUESTED,
                $actor,
                "Return {$return->reference} created — {$this->reasonLabel($reason)}.",
                returnId: $return->id,
            );

            event(new ReturnRequested($return, $order, $actor, $role));

            return $return->load([
                'order.seller.city',
                'order.city',
                'order.sector',
                'createdBy.roles',
                'currentLocationCity',
                'statusHistories.changedBy',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function updateCustomerData(OrderReturn $return, User $actor, array $data): OrderReturn
    {
        if (! $actor->hasPermission('returns.edit_customer_data') && ! $actor->hasPermission('returns.manage')) {
            if (! ($actor->canCreateReturnRequest() && $return->order?->seller_id === $actor->id)) {
                throw new AuthorizationException('Missing permission to edit return customer data.');
            }
        }

        if (! $return->canEditCustomerData()) {
            throw ValidationException::withMessages([
                'return' => 'Customer data can only be updated when the return is CREATED or IN_TRANSIT_TO_SELLER.',
            ]);
        }

        $return->update([
            'updated_customer_name' => $data['updated_customer_name'] ?? $return->updated_customer_name,
            'updated_customer_phone' => $data['updated_customer_phone'] ?? $return->updated_customer_phone,
            'updated_address' => $data['updated_address'] ?? $return->updated_address,
            'updated_city_id' => $data['updated_city_id'] ?? $return->updated_city_id,
        ]);

        return $return->refresh()->load([
            'order.seller.city',
            'order.city',
            'updatedCity',
            'currentLocationCity',
            'statusHistories.changedBy',
        ]);
    }

    public function applyStatus(
        OrderReturn $return,
        ReturnStatus $toStatus,
        User $actor,
        ?string $comment = null,
        ?string $fromStatus = null,
        ?int $locationCityId = null,
    ): OrderReturn {
        $from = $fromStatus ?? ($return->status instanceof ReturnStatus ? $return->status->value : $return->status);

        return DB::transaction(function () use ($return, $toStatus, $actor, $comment, $from, $locationCityId): OrderReturn {
            $updates = ['status' => $toStatus->value];

            if ($locationCityId) {
                $updates['current_location_city_id'] = $locationCityId;
            }

            $return->update($updates);
            $return->recordStatus($toStatus, $actor, $from, $comment);

            $this->syncOrderStatus($return, $toStatus, $actor, $comment);

            return $return->refresh()->load([
                'order.seller.city',
                'order.city',
                'currentLocationCity',
                'updatedCity',
                'createdBy.roles',
                'statusHistories.changedBy',
            ]);
        });
    }

    /**
     * Retrieve orders eligible for seller-initiated return.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Order>
     */
    public function getEligibleOrders(User $seller, array $filters = []): Collection
    {
        $query = Order::query()
            ->ownedBy($seller->id)
            ->whereIn('status', self::eligibleOrderStatuses(ReturnInitiatedByRole::SELLER));

        $this->applyEligibleReturnConstraints($query);

        $query->with(['city', 'sector', 'seller.city'])->orderByDesc('created_at');

        $this->applyEligibleOrderFilters($query, $filters);

        return $query->get();
    }

    /**
     * Orders eligible for admin-initiated returns (all sellers).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Order>
     */
    public function getEligibleOrdersForAdmin(array $filters = []): Collection
    {
        $query = Order::query()
            ->whereIn('status', self::eligibleOrderStatuses(ReturnInitiatedByRole::ADMIN));

        $this->applyEligibleReturnConstraints($query);

        $query->with(['city', 'sector', 'seller.city'])->orderByDesc('created_at');

        $this->applyEligibleOrderFilters($query, $filters);

        if (! empty($filters['seller_id'])) {
            $query->where('seller_id', (int) $filters['seller_id']);
        }

        return $query->get();
    }

    private function applyEligibleReturnConstraints(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereNull('return_id')
                ->orWhereHas('orderReturn', fn (Builder $inner) => $inner->where(
                    'status',
                    ReturnStatus::CANCELLED->value
                ));
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyEligibleOrderFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where('tracking_number', 'like', '%'.$search.'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function sellerOptions(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', \App\Models\Role::SELLER))
            ->orderBy('first_name')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email']);
    }

    private function syncOrderStatus(OrderReturn $return, ReturnStatus $toStatus, User $actor, ?string $comment): void
    {
        $order = $return->order;

        if (! $order) {
            return;
        }

        $orderStatus = match ($toStatus) {
            ReturnStatus::IN_TRANSIT_TO_DEPOT => OrderStatus::RETURN_IN_PROGRESS,
            ReturnStatus::DELIVERED_TO_SELLER => OrderStatus::RETURNED,
            ReturnStatus::CANCELLED => $this->resolveCancelOrderStatus($order),
            default => null,
        };

        if (! $orderStatus) {
            return;
        }

        $orderUpdates = ['status' => $orderStatus->value];

        // The parcel is back in the seller's hands: stamp the date the invoice
        // will quote for this line.
        if ($toStatus === ReturnStatus::DELIVERED_TO_SELLER) {
            $orderUpdates['is_returned'] = true;
            $orderUpdates['returned_at'] = now();
        }

        if ($toStatus === ReturnStatus::CANCELLED) {
            $orderUpdates['return_id'] = null;
            $orderUpdates['is_returned'] = false;
            $orderUpdates['returned_at'] = null;
        }

        $order->update($orderUpdates);

        $order->recordStatus(
            $orderStatus,
            $actor,
            $comment ?? "Return {$return->reference} status: {$toStatus->label()}.",
            returnId: $toStatus === ReturnStatus::CANCELLED ? null : $return->id,
        );
    }

    private function resolveCancelOrderStatus(Order $order): OrderStatus
    {
        $previous = $order->statusHistories()
            ->whereNotIn('status', [
                OrderStatus::RETURN_REQUESTED->value,
                OrderStatus::RETURN_IN_PROGRESS->value,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('status');

        if ($previous instanceof OrderStatus) {
            return $previous;
        }

        if (is_string($previous) && OrderStatus::tryFrom($previous)) {
            return OrderStatus::from($previous);
        }

        return OrderStatus::FAILED;
    }

    private function assertCanCreate(User $actor, ReturnInitiatedByRole $role, Order $order): void
    {
        $allowed = match ($role) {
            ReturnInitiatedByRole::DRIVER => $actor->canCreateDriverReturn(),
            ReturnInitiatedByRole::SELLER => $actor->canCreateReturnRequest()
                && $order->seller_id === $actor->id,
            ReturnInitiatedByRole::ADMIN, ReturnInitiatedByRole::SYSTEM => false,
        };

        if (! $allowed) {
            throw new AuthorizationException(__('returns.errors.create_forbidden'));
        }
    }

    private function reasonLabel(string $reason): string
    {
        $enum = ReturnReason::tryFrom($reason);

        return $enum ? $enum->label() : $reason;
    }
}
