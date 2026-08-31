<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\UserStatus;
use App\Events\ReturnRequested;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    /**
     * Permissions that let a driver close a return at the seller's door. A
     * driver holding none of them would be given a parcel he cannot sign off,
     * which is why the hand-back dropdown never offers him.
     *
     * @var array<int, string>
     */
    private const HAND_BACK_PERMISSIONS = [
        'returns.transition.to_delivered_to_vendor',
        'returns.update_status',
        'returns.manage',
    ];

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
                OrderStatus::READY_TO_RETURN->value,
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
                OrderStatus::READY_TO_RETURN->value,
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
                'return' => 'Customer data can only be updated while the return is CREATED or IN_DELIVERY_TO_VENDOR.',
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
     * Name the driver who will carry the parcel back to the seller.
     *
     * Kept separate from the transition so the assignment can be corrected on a
     * return already out for restitution — a driver falling ill mid-round is
     * not a reason to rewind the workflow.
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function assignDriver(OrderReturn $return, User $driver, User $actor): OrderReturn
    {
        if (! $this->canAssignDrivers($actor)) {
            throw new AuthorizationException(__('returns.errors.assign_forbidden'));
        }

        $status = $return->status instanceof ReturnStatus ? $return->status : ReturnStatus::from($return->status);

        if (! in_array($status, [ReturnStatus::ARRIVED_VENDOR_HUB, ReturnStatus::IN_DELIVERY_TO_VENDOR], true)) {
            throw ValidationException::withMessages([
                'driver_id' => __('returns.errors.assign_wrong_status'),
            ]);
        }

        if (! $this->isEligibleDriver($driver, $return)) {
            throw ValidationException::withMessages([
                'driver_id' => __('returns.errors.driver_not_eligible'),
            ]);
        }

        if ((int) $return->assigned_to === $driver->id) {
            return $return;
        }

        $return->update([
            'assigned_to' => $driver->id,
            'assigned_at' => now(),
        ]);

        $return->recordStatus(
            $status,
            $actor,
            $status->value,
            __('returns.history.driver_assigned', ['name' => $driver->full_name]),
        );

        return $return;
    }

    public function canAssignDrivers(User $actor): bool
    {
        return $actor->hasPermission('returns.manage')
            || $actor->hasPermission('returns.update_status')
            || $actor->hasPermission('returns.transition.to_in_delivery_to_vendor');
    }

    /**
     * Drivers who may be handed this parcel: they work the city it sits in, and
     * they hold a grant that lets them close the return once delivered.
     *
     * @return Collection<int, User>
     */
    public function driverOptions(?int $cityId = null): Collection
    {
        return $this->driverQuery($cityId)
            ->orderBy('first_name')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name', 'email', 'phone_number', 'city_id']);
    }

    private function isEligibleDriver(User $driver, OrderReturn $return): bool
    {
        return $this->driverQuery($return->handBackCityId())
            ->whereKey($driver->id)
            ->exists();
    }

    /**
     * @return Builder<User>
     */
    private function driverQuery(?int $cityId): Builder
    {
        $query = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::DRIVER))
            ->where(function (Builder $q): void {
                // Granted through a role, or pinned directly on the user.
                $q->whereHas('roles.permissions', fn (Builder $p) => $p->whereIn('name', self::HAND_BACK_PERMISSIONS))
                    ->orWhereHas('permissions', fn (Builder $p) => $p->whereIn('name', self::HAND_BACK_PERMISSIONS));
            });

        if ($cityId) {
            $query->coveringCity($cityId);
        }

        return $query;
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
            ->whereHas('roles', fn ($q) => $q->where('name', Role::SELLER))
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
            ReturnStatus::RECEIVED_AT_HUB => OrderStatus::RETURN_IN_PROGRESS,
            ReturnStatus::DELIVERED_TO_VENDOR => OrderStatus::RETURNED,
            ReturnStatus::CANCELLED => $this->resolveCancelOrderStatus($order),
            default => null,
        };

        if (! $orderStatus) {
            $this->recordReturnProgress($order, $return, $toStatus, $actor, $comment);

            return;
        }

        $orderUpdates = ['status' => $orderStatus->value];

        // The parcel is back in the seller's hands: stamp the date the invoice
        // will quote for this line.
        if ($toStatus === ReturnStatus::DELIVERED_TO_VENDOR) {
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

    /**
     * Mirror a return step that does not move the order onto the order timeline.
     *
     * The order sits on RETURN_IN_PROGRESS from the hub drop-off until the
     * hand-back, which is correct but tells a seller nothing about where his
     * parcel actually is. Stamping the unchanged status — the same trick the
     * delivery attempts use — turns those three weeks into a readable trail.
     */
    private function recordReturnProgress(
        Order $order,
        OrderReturn $return,
        ReturnStatus $toStatus,
        User $actor,
        ?string $comment,
    ): void {
        if ($toStatus->step() === null) {
            return;
        }

        $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::tryFrom((string) $order->status);

        if (! $status) {
            return;
        }

        $order->recordStatus(
            $status,
            $actor,
            $comment ?? "Return {$return->reference}: {$toStatus->label()}.",
            returnId: $return->id,
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

        return OrderStatus::READY_TO_RETURN;
    }

    private function assertCanCreate(User $actor, ReturnInitiatedByRole $role, Order $order): void
    {
        $allowed = match ($role) {
            ReturnInitiatedByRole::DRIVER => $actor->canCreateDriverReturn(),
            ReturnInitiatedByRole::SELLER => $actor->canCreateReturnRequest()
                && $order->seller_id === $actor->id,
            // Back-office staff open returns on behalf of a seller who called
            // in, or for a parcel a driver forgot to declare.
            ReturnInitiatedByRole::ADMIN => $actor->hasPermission('returns.manage'),
            ReturnInitiatedByRole::SYSTEM => false,
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
