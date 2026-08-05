<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrderDriverAutoAssignmentService
{
    public function __construct(
        private readonly OrderAuditService $auditService,
    ) {}

    /**
     * Assign a driver to the order based on its delivery sector.
     * Skips when the order already has a driver or no sector is set.
     */
    public function assignBySector(Order $order): Order
    {
        if ($order->driver_id || ! $order->sector_id) {
            return $order;
        }

        $driver = $this->firstDriverForSector((int) $order->sector_id);

        return $driver ? $this->attach($order, $driver) : $order;
    }

    /**
     * Assign a driver once the parcel is already in its delivery city.
     *
     * Used by the stock flow, where a prepared order whose depot sits in the
     * customer's city never travels and so never passes the transfer reception
     * that normally hands it to a driver.
     *
     * The sector comes first because it is the finer answer, but an order whose
     * sector nobody covers still has to reach someone, so any driver working the
     * city will do. "The first" is the lowest user id, which keeps the choice
     * repeatable instead of depending on row order.
     */
    public function assignForDeliveryCity(Order $order): Order
    {
        if ($order->driver_id || ! $order->city_id) {
            return $order;
        }

        $driver = ($order->sector_id ? $this->firstDriverForSector((int) $order->sector_id) : null)
            ?? $this->firstDriverForCity((int) $order->city_id);

        return $driver ? $this->attach($order, $driver) : $order;
    }

    private function firstDriverForSector(int $sectorId): ?User
    {
        return $this->drivers()
            ->whereHas('sectors', fn (Builder $q) => $q->where('sectors.id', $sectorId))
            ->first();
    }

    private function firstDriverForCity(int $cityId): ?User
    {
        return $this->drivers()
            ->whereHas('sectors', fn (Builder $q) => $q->where('sectors.city_id', $cityId))
            ->first();
    }

    /**
     * @return Builder<User>
     */
    private function drivers(): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::DRIVER))
            ->orderBy('id');
    }

    private function attach(Order $order, User $driver): Order
    {
        $order->forceFill([
            'driver_id' => $driver->id,
            'assigned_at' => now(),
        ])->save();

        $this->auditService->recordDriverAssignment($order, null, $driver, automatic: true);

        return $order->refresh();
    }
}
