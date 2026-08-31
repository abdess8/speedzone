<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The bulk restitution round.
 *
 * A hub clearing its return shelf scans a pile of parcels, puts a driver's name
 * on each, and sends them all out at once. Everything here is a batch-shaped
 * wrapper over the same rules a single return goes through — nothing is
 * relaxed because the screen is faster.
 */
class ReturnHandBackService
{
    public function __construct(
        private readonly ReturnTransitionService $transitions,
        private readonly ReturnService $returns,
    ) {}

    /**
     * Resolve a scanned reference into a row for the batch table.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function validateScan(User $actor, string $input): array
    {
        $this->assertCanDispatch($actor);

        $return = $this->resolve($input);

        if (! $return) {
            return ['valid' => false, 'message' => __('returns.errors.scan_not_found')];
        }

        $status = $return->status instanceof ReturnStatus ? $return->status : ReturnStatus::from($return->status);

        if ($status !== ReturnStatus::ARRIVED_VENDOR_HUB) {
            return [
                'valid' => false,
                'message' => __('returns.errors.not_awaiting_hand_back', ['status' => $status->label()]),
                'row' => $this->row($return),
            ];
        }

        return ['valid' => true, 'row' => $this->row($return)];
    }

    /**
     * Send the whole batch out.
     *
     * A parcel that another agent moved in the meantime must not cost the other
     * nineteen their round, so each line is settled on its own and the failures
     * are reported back rather than thrown.
     *
     * @param  array<int, array{reference?: string, id?: int, driver_id: int}>  $items
     * @return array{dispatched: int, failures: array<int, array{reference: string, message: string}>}
     *
     * @throws AuthorizationException
     */
    public function dispatchBatch(User $actor, array $items, ?string $comment = null): array
    {
        $this->assertCanDispatch($actor);

        $dispatched = 0;
        $failures = [];

        foreach ($items as $item) {
            $reference = (string) ($item['reference'] ?? $item['id'] ?? '');

            try {
                $return = $this->resolveItem($item);

                if (! $return) {
                    $failures[] = ['reference' => $reference, 'message' => __('returns.errors.scan_not_found')];

                    continue;
                }

                $reference = $return->reference;
                $driver = User::query()->find($item['driver_id'] ?? null);

                if (! $driver) {
                    $failures[] = ['reference' => $reference, 'message' => __('returns.errors.driver_required')];

                    continue;
                }

                $this->transitions->handBack($return, $actor, $driver, $comment);
                $dispatched++;
            } catch (ValidationException $e) {
                $failures[] = ['reference' => $reference, 'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage()];
            } catch (AuthorizationException $e) {
                $failures[] = ['reference' => $reference, 'message' => $e->getMessage()];
            } catch (\Throwable $e) {
                Log::error('Return hand-back failed', ['reference' => $reference, 'exception' => $e]);
                $failures[] = ['reference' => $reference, 'message' => __('returns.errors.hand_back_failed')];
            }
        }

        return ['dispatched' => $dispatched, 'failures' => $failures];
    }

    /**
     * Returns parked at a vendor hub, i.e. the shelf the screen is clearing.
     *
     * @return Collection<int, OrderReturn>
     */
    public function pending(?int $cityId = null)
    {
        return OrderReturn::query()
            ->awaitingHandBack($cityId)
            ->with(['order.seller.city', 'currentLocationCity', 'assignedDriver'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (OrderReturn $return) => $this->row($return));
    }

    /**
     * @return array<string, mixed>
     */
    public function row(OrderReturn $return): array
    {
        $status = $return->status instanceof ReturnStatus ? $return->status : ReturnStatus::from($return->status);

        return [
            'id' => $return->id,
            'reference' => $return->reference,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'order_tracking' => $return->order?->tracking_number,
            'seller_name' => $return->order?->seller?->full_name,
            'city_id' => $return->handBackCityId(),
            'city_name' => $return->currentLocationCity?->name ?? $return->order?->seller?->city?->name,
            'assigned_to' => $return->assigned_to,
            'assigned_driver_name' => $return->assignedDriver?->full_name,
        ];
    }

    /**
     * @param  array{reference?: string, id?: int}  $item
     */
    private function resolveItem(array $item): ?OrderReturn
    {
        if (! empty($item['id'])) {
            return OrderReturn::query()->with('order.seller')->find((int) $item['id']);
        }

        return $this->resolve((string) ($item['reference'] ?? ''));
    }

    /**
     * Accept a return reference, a scan URL, or the order's tracking number —
     * whichever label happens to be facing up on the parcel.
     */
    private function resolve(string $input): ?OrderReturn
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (preg_match('#/returns/([A-Za-z0-9-]+)#i', $input, $matches)) {
            $input = $matches[1];
        } elseif (preg_match('#/orders/([A-Za-z0-9-]+)#i', $input, $matches)) {
            $input = $matches[1];
        }

        $reference = strtoupper($input);

        $return = OrderReturn::query()
            ->with(['order.seller.city', 'currentLocationCity', 'assignedDriver'])
            ->where('reference', $reference)
            ->first();

        if ($return) {
            return $return;
        }

        return OrderReturn::query()
            ->with(['order.seller.city', 'currentLocationCity', 'assignedDriver'])
            ->whereHas('order', fn ($q) => $q->where('tracking_number', $reference))
            ->active()
            ->first();
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanDispatch(User $actor): void
    {
        if (! $this->returns->canAssignDrivers($actor)) {
            throw new AuthorizationException(__('returns.errors.assign_forbidden'));
        }
    }
}
