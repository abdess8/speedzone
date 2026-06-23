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

        $driver = User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::DRIVER))
            ->whereHas('sectors', fn (Builder $q) => $q->where('sectors.id', $order->sector_id))
            ->orderBy('id')
            ->first();

        if (! $driver) {
            return $order;
        }

        $order->forceFill([
            'driver_id' => $driver->id,
            'assigned_at' => now(),
        ])->save();

        $this->auditService->recordDriverAssignment($order, null, $driver, automatic: true);

        return $order->refresh();
    }
}
