<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransferStatus;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Role;
use App\Models\Transfer;
use App\Models\User;
use App\Support\DashboardDateRange;
use App\Support\DashboardPermissions;
use App\Support\StoreContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Dashboard status buckets for charts (maps many enum values to one label).
     *
     * @var array<string, string>
     */
    private const STATUS_BUCKETS = [
        OrderStatus::CREATED->value => 'created',
        OrderStatus::PICKUP_REQUESTED->value => 'waiting_pickup',
        OrderStatus::WAITING_PICKUP->value => 'waiting_pickup',
        OrderStatus::PICKED_UP->value => 'picked_up',
        OrderStatus::IN_DEPOT->value => 'at_agency',
        OrderStatus::TRANSFER_CREATED->value => 'at_agency',
        OrderStatus::IN_TRANSIT->value => 'in_transit',
        OrderStatus::RECEIVED_IN_DESTINATION->value => 'received',
        OrderStatus::IN_DELIVERY_CITY->value => 'received',
        OrderStatus::OUT_FOR_DELIVERY->value => 'out_for_delivery',
        OrderStatus::DELIVERED->value => 'delivered',
        OrderStatus::FAILED->value => 'not_delivered',
        OrderStatus::REJECTED->value => 'not_delivered',
        OrderStatus::CANCELED->value => 'cancelled',
        OrderStatus::RETURN_REQUESTED->value => 'returned',
        OrderStatus::RETURN_IN_PROGRESS->value => 'returned',
        OrderStatus::RETURNED->value => 'returned',
    ];

    /**
     * @return array<string, string>
     */
    private function statusBucketLabels(): array
    {
        return [
            'created' => __('dashboard.status_buckets.created'),
            'waiting_pickup' => __('dashboard.status_buckets.waiting_pickup'),
            'picked_up' => __('dashboard.status_buckets.picked_up'),
            'at_agency' => __('dashboard.status_buckets.at_agency'),
            'in_transit' => __('dashboard.status_buckets.in_transit'),
            'received' => __('dashboard.status_buckets.received'),
            'out_for_delivery' => __('dashboard.status_buckets.out_for_delivery'),
            'delivered' => __('dashboard.status_buckets.delivered'),
            'not_delivered' => __('dashboard.status_buckets.not_delivered'),
            'returned' => __('dashboard.status_buckets.returned'),
            'cancelled' => __('dashboard.status_buckets.cancelled'),
        ];
    }

    /**
     * Cache-busting stamp, per user. See {@see self::markStaleFor()}.
     */
    private const FRESHNESS_KEY = 'dashboard:freshness:%d';

    /**
     * @return array<string, mixed>
     */
    public function get(User $user, DashboardDateRange $range): array
    {
        $sections = DashboardPermissions::sectionsFor($user);

        // The active store and the granted sections are part of the key, not just
        // of the payload. A vendor switching shops and a member whose role was
        // just narrowed are both the same user asking the same period, and either
        // one served from the previous answer would be reading someone else's
        // figures.
        $cacheKey = sprintf(
            'dashboard:%d:%s:%s:%s:%s:%s',
            $user->id,
            app(StoreContext::class)->id() ?? 'account',
            app()->getLocale(),
            $range->cacheKeySuffix(),
            implode('', array_map(fn (bool $granted): string => $granted ? '1' : '0', $sections)),
            Cache::get(sprintf(self::FRESHNESS_KEY, $user->id), '0'),
        );

        $ttl = (int) config('performance.dashboard_cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, fn () => $this->build($user, $range, $sections));
    }

    /**
     * Force this user's next dashboard read to be recomputed.
     *
     * The payload is cached for minutes at a time, which is right for a screen
     * someone leaves open — and wrong the instant that same person changes an
     * order themselves and watches the figures not move. Rather than dropping
     * the TTL for everybody, or hunting every period/locale key this user might
     * hold, the stamp is folded into the key: bumping it retires their whole
     * set at once and leaves every other user's cache alone.
     */
    public static function markStaleFor(User $user): void
    {
        Cache::put(
            sprintf(self::FRESHNESS_KEY, $user->id),
            (string) now()->getTimestampMs(),
            now()->addDay(),
        );
    }

    /**
     * @param  array<string, bool>  $sections
     * @return array<string, mixed>
     */
    private function build(User $user, DashboardDateRange $range, array $sections): array
    {
        Carbon::setLocale(app()->getLocale());

        $scoped = $this->scopedOrdersQuery($user);
        $inPeriod = (clone $scoped)->whereBetween('orders.created_at', [$range->start, $range->end]);

        // Aggregates consumed by more than one section are resolved once here.
        // Previously each consumer re-ran its own query, which made the status
        // breakdown, the success gauge, the agent ranking, the city ranking and
        // the seller ranking each execute twice per dashboard build.
        //
        // A section the actor may not read is not merely hidden downstream: its
        // queries never run, so a narrowed role costs less rather than more.
        $statusCounts = $this->statusCounts($inPeriod);
        $agentPerformance = $sections['performance'] ? $this->deliveryAgentsPerformance($inPeriod) : [];
        $cityRanking = $sections['operations'] ? $this->cityRanking($inPeriod) : [];
        $sellerRanking = $sections['network'] ? $this->sellerRanking($inPeriod) : [];
        $successGauge = $this->deliverySuccessGauge($statusCounts);

        return [
            'meta' => [
                'filter' => $range->toMeta(),
                'generated_at' => now()->toIso8601String(),
            ],
            'widgets' => $sections,
            'summary' => $this->buildSummary($user, $scoped, $inPeriod, $range, $statusCounts, $sections),
            'charts' => $this->buildCharts($scoped, $range, $sections, [
                'statusCounts' => $statusCounts,
                'agentPerformance' => $agentPerformance,
                'cityRanking' => $cityRanking,
                'sellerRanking' => $sellerRanking,
                'successGauge' => $successGauge,
            ]),
            'recentOrders' => $this->recentOrders($scoped),
            'recentActivities' => $this->recentActivities($scoped),
            'topCustomers' => $sections['customers'] ? $this->topCustomers($inPeriod) : [],
            'topCities' => $cityRanking,
            'topSellers' => $sellerRanking,
            'paymentMethods' => $sections['financials']
                ? $this->paymentMethods($inPeriod)
                : ['labels' => [], 'series' => [], 'note' => null],
            'deliveryPerformance' => $sections['performance']
                ? [
                    'success_rate' => $successGauge['rate'],
                    'delivered' => $successGauge['delivered'],
                    'failed' => $successGauge['failed'],
                    'top_agents' => $agentPerformance,
                ]
                : [],
            'limitations' => $this->limitations(),
        ];
    }

    /**
     * Order count per status inside the selected period.
     *
     * @return array<string, int>
     */
    private function statusCounts(Builder $inPeriod): array
    {
        return (clone $inPeriod)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function scopedOrdersQuery(User $user): Builder
    {
        $query = Order::query()->whereNull('partner_id');

        if ($user->hasPermission('orders.read.all')) {
            return $query;
        }

        if ($user->hasPermission('orders.read.assigned')) {
            return $query->assignedTo($user->id);
        }

        if ($user->hasPermission('orders.read.own')) {
            return $query->ownedBy($user->accountOwnerId());
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, int>  $statusCounts
     * @param  array<string, bool>  $sections
     * @return array<string, mixed>
     */
    private function buildSummary(
        User $user,
        Builder $scoped,
        Builder $inPeriod,
        DashboardDateRange $range,
        array $statusCounts,
        array $sections,
    ): array {
        $statusCount = fn (array $statuses): int => array_sum(
            array_map(fn (string $status) => $statusCounts[$status] ?? 0, $statuses)
        );

        // Every status bucket is already known from the grouped count, so the
        // total and the delivered/failed figures need no extra round trips.
        $ordersTotal = array_sum($statusCounts);
        $deliveredCount = $statusCount([OrderStatus::DELIVERED->value]);
        $failedCount = $statusCount([OrderStatus::FAILED->value, OrderStatus::REJECTED->value]);

        $attempted = $deliveredCount + $failedCount;
        $successRate = $attempted > 0 ? round(($deliveredCount / $attempted) * 100, 1) : null;

        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        // Today always falls inside the current month, so one scan of the month
        // yields both the daily and the monthly figure.
        $createdThisMonth = (clone $scoped)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('COUNT(*) as orders_month')
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as orders_today', [
                $todayStart,
                $todayEnd,
            ])
            ->first();

        $summary = [
            'orders_today' => (int) ($createdThisMonth?->orders_today ?? 0),
            'orders_this_month' => (int) ($createdThisMonth?->orders_month ?? 0),
            'orders_in_period' => $ordersTotal,
            'delivered_orders' => $deliveredCount,
            'late_deliveries' => null,
        ];

        if ($sections['operations']) {
            $summary += [
                'pending_pickup' => $statusCount([
                    OrderStatus::CREATED->value,
                    OrderStatus::PICKUP_REQUESTED->value,
                    OrderStatus::WAITING_PICKUP->value,
                ]),
                'in_transit' => $statusCount([OrderStatus::IN_TRANSIT->value]),
                'out_for_delivery' => $statusCount([OrderStatus::OUT_FOR_DELIVERY->value]),
                'returned_orders' => $statusCount([
                    OrderStatus::RETURNED->value,
                    OrderStatus::RETURN_REQUESTED->value,
                    OrderStatus::RETURN_IN_PROGRESS->value,
                ]),
                'cancelled_orders' => $statusCount([OrderStatus::CANCELED->value]),
                'orders_at_agency' => $statusCount([
                    OrderStatus::IN_DEPOT->value,
                    OrderStatus::TRANSFER_CREATED->value,
                ]),
                'failed_deliveries' => $failedCount,
                'pending_transfers' => $this->countPendingTransfers($user, $scoped),
            ];
        }

        if ($sections['financials']) {
            $summary += $this->moneySummary($scoped, $inPeriod, $ordersTotal, $todayStart, $todayEnd, $monthStart, $monthEnd);
        }

        if ($sections['performance']) {
            $summary += [
                'delivery_success_rate' => $successRate,
                'average_delivery_time_hours' => $this->averageDeliveryHours($inPeriod),
            ];
        }

        if ($sections['customers']) {
            $summary['new_customers'] = $this->countNewCustomers($scoped, $range);
        }

        if ($sections['network']) {
            $summary += $this->headcountSummary($scoped, $inPeriod);
        }

        return $summary;
    }

    /**
     * Money figures of the period, in a single pass.
     *
     * @return array<string, float>
     */
    private function moneySummary(
        Builder $scoped,
        Builder $inPeriod,
        int $ordersTotal,
        Carbon $todayStart,
        Carbon $todayEnd,
        Carbon $monthStart,
        Carbon $monthEnd,
    ): array {
        $totals = (clone $inPeriod)
            ->selectRaw('AVG(total_amount) as avg_order_value')
            ->selectRaw('SUM(CASE WHEN status = ? THEN delivery_price ELSE 0 END) as revenue_total', [
                OrderStatus::DELIVERED->value,
            ])
            ->selectRaw('SUM(CASE WHEN status = ? AND payment_method = ? THEN order_amount ELSE 0 END) as cod_collected', [
                OrderStatus::DELIVERED->value,
                PaymentMethod::CASH->value,
            ])
            ->first();

        $deliveredThisMonth = (clone $scoped)
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereBetween('delivered_at', [$monthStart, $monthEnd])
            ->selectRaw('SUM(delivery_price) as revenue_month')
            ->selectRaw('SUM(CASE WHEN delivered_at BETWEEN ? AND ? THEN delivery_price ELSE 0 END) as revenue_today', [
                $todayStart,
                $todayEnd,
            ])
            ->first();

        $cashPendingStatuses = [
            OrderStatus::CREATED->value,
            OrderStatus::PICKUP_REQUESTED->value,
            OrderStatus::WAITING_PICKUP->value,
            OrderStatus::PICKED_UP->value,
            OrderStatus::IN_DEPOT->value,
            OrderStatus::TRANSFER_CREATED->value,
            OrderStatus::IN_TRANSIT->value,
            OrderStatus::RECEIVED_IN_DESTINATION->value,
            OrderStatus::IN_DELIVERY_CITY->value,
            OrderStatus::OUT_FOR_DELIVERY->value,
        ];

        $cashToCollect = (float) (clone $scoped)
            ->where('payment_method', PaymentMethod::CASH->value)
            ->whereIn('status', $cashPendingStatuses)
            ->sum('order_amount');

        return [
            'cash_to_collect' => round($cashToCollect, 2),
            'cod_collected' => round((float) ($totals?->cod_collected ?? 0), 2),
            'revenue_in_period' => round((float) ($totals?->revenue_total ?? 0), 2),
            'revenue_today' => round((float) ($deliveredThisMonth?->revenue_today ?? 0), 2),
            'revenue_this_month' => round((float) ($deliveredThisMonth?->revenue_month ?? 0), 2),
            'average_order_value' => $ordersTotal > 0 ? round((float) ($totals?->avg_order_value ?? 0), 2) : 0.0,
        ];
    }

    private function averageDeliveryHours(Builder $inPeriod): ?float
    {
        $hours = (clone $inPeriod)
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereNotNull('delivered_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, delivered_at)) as avg_hours')
            ->value('avg_hours');

        return $hours === null ? null : round((float) $hours, 1);
    }

    /**
     * Sellers and drivers currently carrying work.
     *
     * @return array<string, int>
     */
    private function headcountSummary(Builder $scoped, Builder $inPeriod): array
    {
        // Counting through a subquery keeps the id list inside MySQL instead of
        // pulling every distinct seller/driver id into PHP and sending it back
        // as a giant IN (...) list.
        $activeSellers = User::query()
            ->whereIn('id', (clone $inPeriod)->select('orders.seller_id'))
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::SELLER))
            ->count();

        $activeDriverStatuses = [
            OrderStatus::OUT_FOR_DELIVERY->value,
            OrderStatus::IN_DELIVERY_CITY->value,
            OrderStatus::RECEIVED_IN_DESTINATION->value,
        ];

        $activeDeliveryAgents = User::query()
            ->whereIn('id', (clone $scoped)
                ->whereIn('status', $activeDriverStatuses)
                ->whereNotNull('driver_id')
                ->select('orders.driver_id'))
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::DRIVER))
            ->count();

        return [
            'active_sellers' => $activeSellers,
            'active_delivery_agents' => $activeDeliveryAgents,
        ];
    }

    /**
     * Transfers still waiting to leave, seen from where the reader stands.
     *
     * A transfer carries parcels from several sellers, and the table has no
     * store_id of its own, so the global scope cannot narrow it. Left unqualified
     * the tile told a vendor how many lorries the whole network was loading —
     * a figure he can neither act on nor recognise. Anyone without account-wide
     * order visibility therefore only counts the transfers that actually carry
     * one of the orders he can read.
     */
    private function countPendingTransfers(User $user, Builder $scoped): int
    {
        $pending = Transfer::query()
            ->whereIn('status', [TransferStatus::CREATED->value, TransferStatus::WAITING_DISPATCH->value]);

        if (! $user->hasPermission('orders.read.all')) {
            $pending->whereHas(
                'orders',
                fn (Builder $orders) => $orders->whereIn('orders.id', (clone $scoped)->select('orders.id'))
            );
        }

        return $pending->count();
    }

    /**
     * Customers whose very first order falls inside the period.
     *
     * Resolved entirely in SQL: the previous implementation pulled every
     * distinct phone number of the period into PHP and sent them straight back
     * as an IN (...) list, then counted the hydrated rows in PHP.
     */
    private function countNewCustomers(Builder $scoped, DashboardDateRange $range): int
    {
        $firstOrderPerCustomer = (clone $scoped)
            ->select('customer_phone')
            ->groupBy('customer_phone')
            ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$range->start, $range->end]);

        return (int) DB::query()->fromSub($firstOrderPerCustomer, 'first_orders')->count();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, bool>  $sections
     * @param  array<string, mixed>  $shared
     * @return array<string, mixed>
     */
    private function buildCharts(Builder $scoped, DashboardDateRange $range, array $sections, array $shared): array
    {
        $empty = ['labels' => [], 'series' => []];

        return [
            'ordersByDay' => $this->ordersByDay($scoped, $range),
            'ordersByStatus' => $sections['operations'] ? $this->ordersByStatus($shared['statusCounts']) : $empty,
            'ordersByCity' => [
                'labels' => array_column($shared['cityRanking'], 'city_name'),
                'series' => array_column($shared['cityRanking'], 'orders'),
            ],
            'monthlyRevenue' => $sections['financials'] ? $this->monthlyRevenue($scoped, $range) : $empty,
            'deliverySuccessRate' => $sections['performance'] ? $shared['successGauge'] : [],
            'ordersPerSeller' => [
                'labels' => array_column($shared['sellerRanking'], 'seller_name'),
                'series' => array_column($shared['sellerRanking'], 'orders'),
            ],
            'deliveryAgentsPerformance' => $shared['agentPerformance'],
        ];
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function ordersByDay(Builder $scoped, DashboardDateRange $range): array
    {
        $chartEnd = $range->end->copy()->startOfDay();
        $chartStart = $chartEnd->copy()->subDays(29)->startOfDay();

        $rows = (clone $scoped)
            ->whereBetween('created_at', [$chartStart, $range->end])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $series = [];
        $cursor = $chartStart->copy();

        while ($cursor->lte($chartEnd)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->translatedFormat('d M');
            $series[] = (int) ($rows[$key] ?? 0);
            $cursor->addDay();
        }

        return compact('labels', 'series');
    }

    /**
     * @param  array<string, int>  $statusCounts
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function ordersByStatus(array $statusCounts): array
    {
        $bucketed = [];
        foreach ($statusCounts as $status => $count) {
            $bucket = self::STATUS_BUCKETS[$status] ?? 'created';
            $bucketed[$bucket] = ($bucketed[$bucket] ?? 0) + (int) $count;
        }

        $labels = [];
        $series = [];
        foreach ($this->statusBucketLabels() as $key => $label) {
            $value = (int) ($bucketed[$key] ?? 0);
            if ($value > 0) {
                $labels[] = $label;
                $series[] = $value;
            }
        }

        return compact('labels', 'series');
    }

    /**
     * Top cities by order volume. Feeds both the "top cities" table and the
     * ordersByCity chart.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cityRanking(Builder $inPeriod): array
    {
        return (clone $inPeriod)
            ->join('cities', 'cities.id', '=', 'orders.city_id')
            ->selectRaw('cities.id, cities.name, COUNT(*) as orders_count')
            ->groupBy('cities.id', 'cities.name')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'city_id' => (int) $row->id,
                'city_name' => $row->name,
                'orders' => (int) $row->orders_count,
            ])
            ->all();
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, float>}
     */
    private function monthlyRevenue(Builder $scoped, DashboardDateRange $range): array
    {
        $end = $range->end->copy()->startOfMonth();
        $start = $end->copy()->subMonths(11)->startOfMonth();

        $rows = (clone $scoped)
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$start, $range->end])
            ->selectRaw('DATE_FORMAT(delivered_at, "%Y-%m") as month, SUM(delivery_price) as revenue')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('revenue', 'month');

        $labels = [];
        $series = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->translatedFormat('M Y');
            $series[] = round((float) ($rows[$key] ?? 0), 2);
            $cursor->addMonth();
        }

        return compact('labels', 'series');
    }

    /**
     * Derived from the already-fetched status breakdown — no query needed.
     *
     * @param  array<string, int>  $statusCounts
     * @return array{delivered: int, failed: int, rate: float|null}
     */
    private function deliverySuccessGauge(array $statusCounts): array
    {
        $delivered = $statusCounts[OrderStatus::DELIVERED->value] ?? 0;
        $failed = ($statusCounts[OrderStatus::FAILED->value] ?? 0)
            + ($statusCounts[OrderStatus::REJECTED->value] ?? 0);
        $attempted = $delivered + $failed;

        return [
            'delivered' => $delivered,
            'failed' => $failed,
            'rate' => $attempted > 0 ? round(($delivered / $attempted) * 100, 1) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deliveryAgentsPerformance(Builder $inPeriod): array
    {
        $rows = (clone $inPeriod)
            ->whereNotNull('driver_id')
            ->join('users', 'users.id', '=', 'orders.driver_id')
            ->selectRaw('users.id as driver_id, users.name as driver_name')
            ->selectRaw('SUM(CASE WHEN orders.status = ? THEN 1 ELSE 0 END) as delivered', [OrderStatus::DELIVERED->value])
            ->selectRaw('SUM(CASE WHEN orders.status IN (?, ?) THEN 1 ELSE 0 END) as failed', [
                OrderStatus::FAILED->value,
                OrderStatus::REJECTED->value,
            ])
            ->selectRaw('AVG(CASE WHEN orders.status = ? AND orders.delivered_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, orders.created_at, orders.delivered_at) END) as avg_hours', [
                OrderStatus::DELIVERED->value,
            ])
            ->groupBy('users.id', 'users.name')
            ->havingRaw('delivered > 0 OR failed > 0')
            ->orderByDesc('delivered')
            ->limit(10)
            ->get();

        return $rows->map(function ($row) {
            $delivered = (int) $row->delivered;
            $failed = (int) $row->failed;
            $attempted = $delivered + $failed;

            return [
                'driver_id' => (int) $row->driver_id,
                'driver_name' => $row->driver_name,
                'delivered' => $delivered,
                'failed' => $failed,
                'success_rate' => $attempted > 0 ? round(($delivered / $attempted) * 100, 1) : null,
                'average_delivery_time_hours' => $row->avg_hours !== null ? round((float) $row->avg_hours, 1) : null,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * Columns formatOrderRow() actually reads. Selecting them explicitly keeps
     * the row narrow instead of hydrating every order column.
     *
     * @var array<int, string>
     */
    private const RECENT_ORDER_COLUMNS = [
        'id', 'tracking_number', 'customer_first_name', 'customer_last_name',
        'customer_phone', 'seller_id', 'city_id', 'driver_id', 'status',
        'payment_method', 'total_amount', 'order_amount', 'created_at',
    ];

    private function recentOrders(Builder $scoped): array
    {
        return (clone $scoped)
            ->select(self::RECENT_ORDER_COLUMNS)
            ->with([
                'city:id,name',
                'seller:id,name,city_id',
                'seller.city:id,name',
                'driver:id,name',
            ])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Order $order) => $this->formatOrderRow($order))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderRow(Order $order): array
    {
        $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::from($order->status);
        $payment = $order->payment_method instanceof PaymentMethod
            ? $order->payment_method
            : PaymentMethod::resolve((string) $order->payment_method);

        return [
            'id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'customer_name' => $order->customer_full_name,
            'customer_phone' => $order->customer_phone,
            'seller_name' => $order->seller?->name,
            'pickup_city' => $order->seller?->city?->name,
            'destination_city' => $order->city?->name,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'status_icon' => $status->icon(),
            'payment_method' => $payment->value,
            'payment_method_label' => $payment->displayLabel(),
            'amount' => (float) $order->total_amount,
            'order_amount' => (float) $order->order_amount,
            'delivery_agent' => $order->driver?->name,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivities(Builder $scoped): array
    {
        $orderIds = (clone $scoped)->select('orders.id');

        // History rows are append-only, so id order matches created_at order.
        // Sorting on the primary key lets MySQL walk the index backwards and
        // stop after 20 matches instead of filesorting the whole table.
        return OrderStatusHistory::query()
            ->select(['id', 'order_id', 'user_id', 'status', 'is_system', 'comment', 'created_at'])
            ->with(['order:id,tracking_number', 'user:id,name'])
            ->whereIn('order_id', $orderIds)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(function (OrderStatusHistory $history) {
                $status = $history->status instanceof OrderStatus
                    ? $history->status
                    : OrderStatus::from($history->status);

                return [
                    'id' => $history->id,
                    'order_id' => $history->order_id,
                    'tracking_number' => $history->order?->tracking_number,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_color' => $status->color(),
                    'status_icon' => $status->icon(),
                    'actor_name' => $history->is_system ? __('dashboard.system') : ($history->user?->name ?? __('dashboard.unknown')),
                    'comment' => $history->comment,
                    'created_at' => $history->created_at?->toIso8601String(),
                    'created_at_human' => $history->created_at?->diffForHumans(),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topCustomers(Builder $inPeriod): array
    {
        $pendingStatuses = [
            OrderStatus::CREATED->value,
            OrderStatus::PICKUP_REQUESTED->value,
            OrderStatus::WAITING_PICKUP->value,
            OrderStatus::PICKED_UP->value,
            OrderStatus::IN_DEPOT->value,
            OrderStatus::TRANSFER_CREATED->value,
            OrderStatus::IN_TRANSIT->value,
            OrderStatus::RECEIVED_IN_DESTINATION->value,
            OrderStatus::IN_DELIVERY_CITY->value,
            OrderStatus::OUT_FOR_DELIVERY->value,
        ];

        $pendingList = implode(',', array_map(fn ($s) => "'{$s}'", $pendingStatuses));

        return (clone $inPeriod)
            ->selectRaw('customer_phone, MAX(customer_first_name) as first_name, MAX(customer_last_name) as last_name')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(CASE WHEN payment_method = ? THEN order_amount ELSE 0 END) as total_cod', [PaymentMethod::CASH->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered', [OrderStatus::DELIVERED->value])
            ->selectRaw("SUM(CASE WHEN status IN ({$pendingList}) THEN 1 ELSE 0 END) as pending")
            ->groupBy('customer_phone')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'customer_name' => trim("{$row->first_name} {$row->last_name}"),
                'phone' => $row->customer_phone,
                'orders' => (int) $row->orders_count,
                'total_cod' => round((float) $row->total_cod, 2),
                'delivered' => (int) $row->delivered,
                'pending' => (int) $row->pending,
            ])
            ->all();
    }

    /**
     * Top sellers by order volume. Feeds both the "top sellers" table and the
     * ordersPerSeller chart.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sellerRanking(Builder $inPeriod): array
    {
        return (clone $inPeriod)
            ->join('users', 'users.id', '=', 'orders.seller_id')
            ->selectRaw('users.id, users.name, COUNT(*) as orders_count')
            ->selectRaw('SUM(CASE WHEN orders.status = ? THEN 1 ELSE 0 END) as delivered', [OrderStatus::DELIVERED->value])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'seller_id' => (int) $row->id,
                'seller_name' => $row->name,
                'orders' => (int) $row->orders_count,
                'delivered' => (int) $row->delivered,
            ])
            ->all();
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, int>, note: string|null}
     */
    private function paymentMethods(Builder $inPeriod): array
    {
        $rows = (clone $inPeriod)
            ->selectRaw('payment_method, COUNT(*) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $labels = [];
        $series = [];

        foreach ($rows as $method => $count) {
            $resolved = PaymentMethod::resolve((string) $method);
            $labels[] = $resolved->label();
            $series[] = (int) $count;
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'note' => __('dashboard.payment_methods_note'),
        ];
    }

    /**
     * Metrics that cannot be computed from current schema.
     *
     * @return array<int, array<string, string>>
     */
    private function limitations(): array
    {
        return [
            [
                'metric' => 'late_deliveries',
                'label' => __('dashboard.limitations.late_deliveries.metric'),
                'reason' => __('dashboard.limitations.late_deliveries.reason'),
            ],
            [
                'metric' => 'payment_method_transfer',
                'label' => __('dashboard.limitations.payment_method_transfer.metric'),
                'reason' => __('dashboard.limitations.payment_method_transfer.reason'),
            ],
        ];
    }
}
