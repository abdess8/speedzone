<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\StatusCounts;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderQueryService
{
    private const SORTABLE = [
        'created_at',
        'tracking_number',
        'order_amount',
        'order_value',
        'delivery_price',
        'status',
    ];

    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_PAGE_SIZE = 25;

    /**
     * Named status buckets backing the sidebar's pre-filtered order views.
     *
     * A single `status` value cannot express "in the middle of being picked up",
     * which spans two statuses, so the shortcuts select a group instead of
     * duplicating the status list in the sidebar definition.
     *
     * @var array<string, array<int, string>>
     */
    public const STATUS_GROUPS = [
        'pickup' => [
            OrderStatus::PICKUP_REQUESTED,
            OrderStatus::WAITING_PICKUP,
        ],
        'delivery' => [
            OrderStatus::IN_DELIVERY_CITY,
            OrderStatus::OUT_FOR_DELIVERY,
        ],
        'failed' => [
            OrderStatus::FAILED,
            OrderStatus::REJECTED,
            OrderStatus::READY_TO_RETURN,
        ],
        'delivered' => [
            OrderStatus::DELIVERED,
        ],
    ];

    /**
     * Statuses covered by a named group, or an empty list when unknown.
     *
     * @return array<int, string>
     */
    public static function statusGroup(?string $group): array
    {
        return array_map(
            static fn (OrderStatus $status): string => $status->value,
            self::STATUS_GROUPS[$group] ?? []
        );
    }

    /**
     * Relations the JSON API contract expects on every order.
     *
     * @var array<int, string>
     */
    public const DEFAULT_RELATIONS = ['city', 'sector', 'seller', 'store'];

    /**
     * Build the filtered, scoped and sorted order query.
     *
     * @param  array<int, string>  $with  Relations to eager load. Callers that
     *                                    render a narrow list can pass a
     *                                    reduced set instead of the full API one.
     */
    public function build(
        Request $request,
        User $user,
        array $with = self::DEFAULT_RELATIONS,
        bool $withStatusFilter = true,
    ): Builder {
        $query = Order::query()->with($with);

        // Native orders only — partner-ingested orders live on /partner-orders.
        $query->whereNull('partner_id');

        if ($user->hasPermission('orders.read.all')) {
            // full access
        } elseif ($user->hasPermission('orders.read.assigned')) {
            $query->assignedTo($user->id);
        } elseif ($user->hasPermission('orders.read.own')) {
            // The vendor account, not the logged-in user: a team member reads
            // his employer's orders. The store scope then narrows the result to
            // the shop he is currently standing on.
            $query->ownedBy($user->accountOwnerId());
        } else {
            $query->whereRaw('1 = 0');
        }

        $this->applyFilters($query, $request, $withStatusFilter);
        $this->applySorting($query, $request);

        return $query;
    }

    /**
     * A pre-filtered sidebar view narrows the cards to its own bucket: a board
     * showing "being picked up" has no use for a Delivered counter.
     *
     * @return array<int, array<string, mixed>>
     */
    public function statusCounts(Request $request, User $user): array
    {
        return StatusCounts::build(
            $this->build($request, $user, with: [], withStatusFilter: false),
            OrderStatus::options(),
            'orders.status',
            self::statusGroup($request->string('status_group')->toString()),
        );
    }

    /**
     * Resolve the requested (and bounded) page size.
     */
    public function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PAGE_SIZE);

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PAGE_SIZE;
        }

        return min($perPage, self::MAX_PAGE_SIZE);
    }

    private function applyFilters(Builder $query, Request $request, bool $withStatusFilter = true): void
    {
        // Tracking number / order number (same field).
        $tracking = $request->input('tracking_number') ?? $request->input('order_number');
        $query->when($tracking, fn (Builder $q, $value) => $q->where('tracking_number', 'like', "%{$value}%"));

        $query->when($request->input('customer_name'), function (Builder $q, $value) {
            $q->where(function (Builder $sub) use ($value) {
                $sub->where('customer_first_name', 'like', "%{$value}%")
                    ->orWhere('customer_last_name', 'like', "%{$value}%")
                    ->orWhereRaw("CONCAT(customer_first_name, ' ', customer_last_name) like ?", ["%{$value}%"]);
            });
        });

        $query->when($request->input('customer_phone'), fn (Builder $q, $value) => $q->where('customer_phone', 'like', "%{$value}%"));

        // Seller by id or name.
        $query->when($request->input('seller'), function (Builder $q, $value) {
            if (is_numeric($value)) {
                $q->where('seller_id', (int) $value);

                return;
            }

            $q->whereHas('seller', function (Builder $sub) use ($value) {
                $sub->where('name', 'like', "%{$value}%")
                    ->orWhere('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%");
            });
        });

        $query->when($request->filled('seller_id'), fn (Builder $q) => $q->where('seller_id', $request->integer('seller_id')));
        $query->when($request->filled('city_id'), fn (Builder $q) => $q->where('city_id', $request->integer('city_id')));
        $query->when($request->filled('sector_id'), fn (Builder $q) => $q->where('sector_id', $request->integer('sector_id')));

        if ($withStatusFilter) {
            $query->when($request->input('status'), function (Builder $q, $value) {
                $values = array_values(array_intersect((array) $value, OrderStatus::values()));
                if ($values !== []) {
                    $q->whereIn('status', $values);
                }
            });
        }

        // Sidebar shortcut. An explicit status filter always wins so that
        // narrowing a pre-filtered view from the filter panel behaves as
        // expected instead of intersecting two status constraints — except when
        // counting for the KPI cards, where the group *is* the population.
        if (! $withStatusFilter || ! $request->filled('status')) {
            $group = self::statusGroup($request->string('status_group')->toString());

            if ($group !== []) {
                $query->whereIn('status', $group);
            }
        }

        $query->when($request->input('payment_method'), fn (Builder $q, $value) => $q->whereIn('payment_method', (array) $value));

        // Creation date range. Compared as an open interval rather than with
        // whereDate(): wrapping the column in DATE() makes MySQL ignore the
        // index on orders.created_at and scan the table.
        $query->when(
            $request->input('created_from'),
            fn (Builder $q, $value) => $q->where('created_at', '>=', $this->startOfDay($value))
        );
        $query->when(
            $request->input('created_to'),
            fn (Builder $q, $value) => $q->where('created_at', '<=', $this->endOfDay($value))
        );

        // Delivery date range — based on when the order reached the DELIVERED status.
        if ($request->filled('delivery_from') || $request->filled('delivery_to')) {
            $query->whereHas('statusHistories', function (Builder $sub) use ($request) {
                $sub->where('status', OrderStatus::DELIVERED->value);
                $sub->when(
                    $request->input('delivery_from'),
                    fn (Builder $s, $value) => $s->where('created_at', '>=', $this->startOfDay($value))
                );
                $sub->when(
                    $request->input('delivery_to'),
                    fn (Builder $s, $value) => $s->where('created_at', '<=', $this->endOfDay($value))
                );
            });
        }

        // Package flags (only filter when explicitly provided).
        if ($request->filled('is_fragile')) {
            $query->where('is_fragile', $request->boolean('is_fragile'));
        }

        if ($request->filled('can_be_opened')) {
            $query->where('can_be_opened', $request->boolean('can_be_opened'));
        }
    }

    private function startOfDay(string $value): CarbonInterface
    {
        return Carbon::parse($value)->startOfDay();
    }

    private function endOfDay(string $value): CarbonInterface
    {
        return Carbon::parse($value)->endOfDay();
    }

    private function applySorting(Builder $query, Request $request): void
    {
        $sort = (string) $request->input('sort', 'created_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $direction)->orderBy('id', 'desc');
    }
}
