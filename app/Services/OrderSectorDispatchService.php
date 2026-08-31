<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Handing a whole round to one driver.
 *
 * A sector *is* the round: the parcels of one sector are driven together, by
 * the driver who covers it. Assigning them one by one is the same choice
 * repeated thirty times, so dispatch is expressed at the level the decision is
 * actually made — a sector and a driver.
 */
class OrderSectorDispatchService
{
    public function __construct(
        private readonly OrderDriverAssignmentService $assignment,
    ) {}

    /**
     * Parcels a round can be built from.
     *
     * Out for delivery only: that is the one status at which a native order
     * accepts a driver, and it is what the dispatcher means by "les commandes
     * out".
     */
    public function dispatchableQuery(User $actor): Builder
    {
        $query = Order::query()
            ->whereNull('partner_id')
            ->whereNotNull('sector_id')
            ->where('status', OrderStatus::OUT_FOR_DELIVERY->value);

        $this->applyReadScope($query, $actor);

        return $query;
    }

    /**
     * The rounds waiting to be dispatched, with the drivers who cover them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rounds(User $actor): array
    {
        $counts = $this->dispatchableQuery($actor)
            ->select('sector_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN driver_id IS NULL THEN 1 ELSE 0 END) as unassigned')
            ->groupBy('sector_id')
            ->get()
            ->keyBy('sector_id');

        if ($counts->isEmpty()) {
            return [];
        }

        return Sector::query()
            ->with(['city:id,name', 'drivers:id,name,first_name,last_name'])
            ->whereIn('id', $counts->keys())
            ->orderBy('name')
            ->get(['id', 'city_id', 'name'])
            ->map(fn (Sector $sector) => [
                'id' => $sector->id,
                'name' => $sector->name,
                'city' => $sector->city?->name,
                'total' => (int) $counts[$sector->id]->total,
                'unassigned' => (int) $counts[$sector->id]->unassigned,
                'drivers' => $sector->drivers
                    ->map(fn (User $driver) => [
                        'id' => $driver->id,
                        'name' => $driver->full_name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Every driver, for the sectors nobody covers yet.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function driverOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::DRIVER))
            ->orderBy('first_name')
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'last_name'])
            ->map(fn (User $driver) => [
                'id' => $driver->id,
                'name' => $driver->full_name,
            ])
            ->all();
    }

    /**
     * Give every dispatchable parcel of a sector to one driver.
     *
     * Parcels already carried by somebody else are left alone unless the
     * dispatcher asks for a reassignment: a round is normally handed out once,
     * and silently taking parcels off another driver's van is not a default.
     *
     * @return array{assigned: int, skipped: int, sector: string, driver: string}
     */
    public function dispatchSector(User $actor, int $sectorId, int $driverId, bool $reassign = false): array
    {
        $this->assertCanAssign($actor);

        $driver = $this->resolveDriver($driverId);
        $sector = Sector::query()->findOrFail($sectorId);

        $query = $this->dispatchableQuery($actor)->where('sector_id', $sector->id);

        if (! $reassign) {
            $query->whereNull('driver_id');
        }

        $result = $this->assignEach($query->get(), $driver, $actor, $sector->name);

        return [
            ...$result,
            'sector' => $sector->name,
            'driver' => $driver->full_name,
        ];
    }

    /**
     * Same hand-off, for the rows the dispatcher ticked himself.
     *
     * @param  array<int, int>  $orderIds
     * @return array{assigned: int, skipped: int, driver: string}
     */
    public function assignSelected(User $actor, array $orderIds, int $driverId): array
    {
        $this->assertCanAssign($actor);

        $driver = $this->resolveDriver($driverId);

        $orders = $this->scopedQuery($actor)->whereIn('id', $orderIds)->get();

        return [
            ...$this->assignEach($orders, $driver, $actor),
            'driver' => $driver->full_name,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{assigned: int, skipped: int}
     */
    private function assignEach($orders, User $driver, User $actor, ?string $sectorName = null): array
    {
        $comment = $sectorName === null
            ? 'Bulk driver assignment.'
            : "Round dispatched for sector {$sectorName}.";

        $assigned = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if ($order->driver_id === $driver->id || ! $this->assignment->canAssign($order, $actor)) {
                $skipped++;

                continue;
            }

            try {
                $this->assignment->assign($order, $driver, $actor, $comment);
                $assigned++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }

    private function resolveDriver(int $driverId): User
    {
        $driver = User::query()->whereKey($driverId)->first();

        if (! $driver?->isDriver()) {
            throw ValidationException::withMessages([
                'driver_id' => __('orders.dispatch.not_a_driver'),
            ]);
        }

        return $driver;
    }

    private function assertCanAssign(User $actor): void
    {
        if (! $actor->hasPermission('driver_invoices.assign_driver')
            && ! $actor->hasPermission('partners.deliveries.manage')) {
            throw new AuthorizationException('Missing required permission to assign drivers.');
        }
    }

    private function scopedQuery(User $actor): Builder
    {
        $query = Order::query()->whereNull('partner_id');

        $this->applyReadScope($query, $actor);

        return $query;
    }

    private function applyReadScope(Builder $query, User $actor): void
    {
        if ($actor->hasPermission('orders.read.all')) {
            return;
        }

        if ($actor->hasPermission('orders.read.assigned')) {
            $query->assignedTo($actor->id);

            return;
        }

        if ($actor->hasPermission('orders.read.own')) {
            $query->ownedBy($actor->accountOwnerId());

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
