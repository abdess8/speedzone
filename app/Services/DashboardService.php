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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    private const CACHE_TTL_SECONDS = 120;

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
     * @return array<string, mixed>
     */
    public function get(User $user, DashboardDateRange $range): array
    {
        $cacheKey = sprintf(
            'dashboard:%d:%s:%s',
            $user->id,
            app()->getLocale(),
            $range->cacheKeySuffix(),
        );

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn () => $this->build($user, $range));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(User $user, DashboardDateRange $range): array
    {
        Carbon::setLocale(app()->getLocale());

        $scoped = $this->scopedOrdersQuery($user);
        $inPeriod = (clone $scoped)->whereBetween('orders.created_at', [$range->start, $range->end]);

        return [
            'meta' => [
                'filter' => $range->toMeta(),
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => $this->buildSummary($scoped, $inPeriod, $range),
            'charts' => $this->buildCharts($scoped, $inPeriod, $range),
            'recentOrders' => $this->recentOrders($scoped),
            'recentActivities' => $this->recentActivities($scoped),
            'topCustomers' => $this->topCustomers($inPeriod),
            'topCities' => $this->topCities($inPeriod),
            'topSellers' => $this->topSellers($inPeriod),
            'paymentMethods' => $this->paymentMethods($inPeriod),
            'deliveryPerformance' => $this->deliveryPerformance($inPeriod),
            'limitations' => $this->limitations(),
        ];
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
            return $query->ownedBy($user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(Builder $scoped, Builder $inPeriod, DashboardDateRange $range): array
    {
        $periodCounts = (clone $inPeriod)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusCount = fn (array $statuses): int => (int) collect($statuses)
            ->sum(fn (string $status) => (int) ($periodCounts[$status] ?? 0));

        $ordersTotal = (int) (clone $inPeriod)->count();

        $deliveredInPeriod = (clone $inPeriod)->where('status', OrderStatus::DELIVERED->value);
        $deliveredCount = (int) (clone $deliveredInPeriod)->count();

        $failedCount = $statusCount([OrderStatus::FAILED->value, OrderStatus::REJECTED->value]);
        $attempted = $deliveredCount + $failedCount;
        $successRate = $attempted > 0 ? round(($deliveredCount / $attempted) * 100, 1) : null;

        $avgDeliveryHours = (clone $deliveredInPeriod)
            ->whereNotNull('delivered_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, delivered_at)) as avg_hours')
            ->value('avg_hours');

        $revenueTotal = (float) (clone $deliveredInPeriod)->sum('delivery_price');

        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $revenueToday = (float) (clone $scoped)
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereBetween('delivered_at', [$todayStart, $todayEnd])
            ->sum('delivery_price');

        $ordersToday = (int) (clone $scoped)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $ordersThisMonth = (int) (clone $scoped)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $revenueThisMonth = (float) (clone $scoped)
            ->where('status', OrderStatus::DELIVERED->value)
            ->whereBetween('delivered_at', [$monthStart, $monthEnd])
            ->sum('delivery_price');

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

        $codCollected = (float) (clone $inPeriod)
            ->where('payment_method', PaymentMethod::CASH->value)
            ->where('status', OrderStatus::DELIVERED->value)
            ->sum('order_amount');

        $avgOrderValue = $ordersTotal > 0
            ? round((float) (clone $inPeriod)->avg('total_amount'), 2)
            : 0.0;

        $activeSellerIds = (clone $inPeriod)->distinct()->pluck('seller_id');
        $activeSellers = User::query()
            ->whereIn('id', $activeSellerIds)
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::SELLER))
            ->count();

        $activeDriverStatuses = [
            OrderStatus::OUT_FOR_DELIVERY->value,
            OrderStatus::IN_DELIVERY_CITY->value,
            OrderStatus::RECEIVED_IN_DESTINATION->value,
        ];

        $activeDriverIds = (clone $scoped)
            ->whereIn('status', $activeDriverStatuses)
            ->whereNotNull('driver_id')
            ->distinct()
            ->pluck('driver_id');

        $activeDeliveryAgents = User::query()
            ->whereIn('id', $activeDriverIds)
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Role::DRIVER))
            ->count();

        $newCustomers = $this->countNewCustomers($scoped, $range);

        $pendingTransfers = Transfer::query()
            ->whereIn('status', [TransferStatus::CREATED->value, TransferStatus::WAITING_DISPATCH->value])
            ->count();

        return [
            'orders_today' => $ordersToday,
            'orders_this_month' => $ordersThisMonth,
            'orders_in_period' => $ordersTotal,
            'delivered_orders' => $deliveredCount,
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
            'cash_to_collect' => round($cashToCollect, 2),
            'cod_collected' => round($codCollected, 2),
            'delivery_success_rate' => $successRate,
            'average_delivery_time_hours' => $avgDeliveryHours !== null ? round((float) $avgDeliveryHours, 1) : null,
            'active_sellers' => $activeSellers,
            'active_delivery_agents' => $activeDeliveryAgents,
            'new_customers' => $newCustomers,
            'revenue_in_period' => round($revenueTotal, 2),
            'revenue_today' => round($revenueToday, 2),
            'revenue_this_month' => round($revenueThisMonth, 2),
            'average_order_value' => $avgOrderValue,
            'pending_transfers' => $pendingTransfers,
            'orders_at_agency' => $statusCount([
                OrderStatus::IN_DEPOT->value,
                OrderStatus::TRANSFER_CREATED->value,
            ]),
            'late_deliveries' => null,
            'failed_deliveries' => $failedCount,
        ];
    }

    private function countNewCustomers(Builder $scoped, DashboardDateRange $range): int
    {
        $phonesInPeriod = (clone $scoped)
            ->whereBetween('created_at', [$range->start, $range->end])
            ->distinct()
            ->pluck('customer_phone');

        if ($phonesInPeriod->isEmpty()) {
            return 0;
        }

        return (int) (clone $scoped)
            ->whereIn('customer_phone', $phonesInPeriod)
            ->select('customer_phone')
            ->groupBy('customer_phone')
            ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$range->start, $range->end])
            ->get()
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCharts(Builder $scoped, Builder $inPeriod, DashboardDateRange $range): array
    {
        return [
            'ordersByDay' => $this->ordersByDay($scoped, $range),
            'ordersByStatus' => $this->ordersByStatus($inPeriod),
            'ordersByCity' => $this->ordersByCity($inPeriod),
            'monthlyRevenue' => $this->monthlyRevenue($scoped, $range),
            'deliverySuccessRate' => $this->deliverySuccessGauge($inPeriod),
            'ordersPerSeller' => $this->ordersPerSeller($inPeriod),
            'deliveryAgentsPerformance' => $this->deliveryAgentsPerformance($inPeriod),
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
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function ordersByStatus(Builder $inPeriod): array
    {
        $raw = (clone $inPeriod)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $bucketed = [];
        foreach ($raw as $status => $count) {
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
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function ordersByCity(Builder $inPeriod): array
    {
        $rows = (clone $inPeriod)
            ->join('cities', 'cities.id', '=', 'orders.city_id')
            ->selectRaw('cities.name as city_name, COUNT(*) as total')
            ->groupBy('cities.id', 'cities.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $rows->pluck('city_name')->all(),
            'series' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
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
     * @return array{delivered: int, failed: int, rate: float|null}
     */
    private function deliverySuccessGauge(Builder $inPeriod): array
    {
        $delivered = (int) (clone $inPeriod)->where('status', OrderStatus::DELIVERED->value)->count();
        $failed = (int) (clone $inPeriod)->whereIn('status', [
            OrderStatus::FAILED->value,
            OrderStatus::REJECTED->value,
        ])->count();
        $attempted = $delivered + $failed;

        return [
            'delivered' => $delivered,
            'failed' => $failed,
            'rate' => $attempted > 0 ? round(($delivered / $attempted) * 100, 1) : null,
        ];
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function ordersPerSeller(Builder $inPeriod): array
    {
        $rows = (clone $inPeriod)
            ->join('users', 'users.id', '=', 'orders.seller_id')
            ->selectRaw('users.name as seller_name, COUNT(*) as total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $rows->pluck('seller_name')->all(),
            'series' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
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
    private function recentOrders(Builder $scoped): array
    {
        return (clone $scoped)
            ->with(['city', 'seller.city', 'driver'])
            ->latest('created_at')
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
        $orderIds = (clone $scoped)->select('id');

        return OrderStatusHistory::query()
            ->with(['order', 'user'])
            ->whereIn('order_id', $orderIds)
            ->latest('created_at')
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
     * @return array<int, array<string, mixed>>
     */
    private function topCities(Builder $inPeriod): array
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
     * @return array<int, array<string, mixed>>
     */
    private function topSellers(Builder $inPeriod): array
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
     * @return array<string, mixed>
     */
    private function deliveryPerformance(Builder $inPeriod): array
    {
        $gauge = $this->deliverySuccessGauge($inPeriod);
        $agents = $this->deliveryAgentsPerformance($inPeriod);

        return [
            'success_rate' => $gauge['rate'],
            'delivered' => $gauge['delivered'],
            'failed' => $gauge['failed'],
            'top_agents' => $agents,
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
