<?php

namespace App\Services\Chatbot\Support;

use App\Support\DashboardDateRange;
use Carbon\Carbon;

/**
 * Maps the timeframes a user talks about ("this week", "last month") onto the
 * {@see DashboardDateRange} the dashboard already understands.
 *
 * The dashboard filter itself is built from an HTTP request and only knows the
 * periods its dropdown offers; conversation needs a couple more (weeks), so the
 * extra ones are constructed here rather than widening the UI contract.
 */
final class KpiTimeframe
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            'today',
            'yesterday',
            'this_week',
            'last_week',
            'last_7_days',
            'last_30_days',
            'this_month',
            'last_month',
            'custom',
        ];
    }

    public static function resolve(string $timeframe, ?string $from = null, ?string $to = null): DashboardDateRange
    {
        return match ($timeframe) {
            'today' => new DashboardDateRange('today', Carbon::today()->startOfDay(), Carbon::today()->endOfDay()),
            'yesterday' => new DashboardDateRange('yesterday', Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()),
            'this_week' => new DashboardDateRange('custom', Carbon::now()->startOfWeek(), Carbon::now()->endOfDay()),
            'last_week' => new DashboardDateRange(
                'custom',
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek(),
            ),
            'last_7_days' => new DashboardDateRange('last_7_days', Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay()),
            'this_month' => new DashboardDateRange('this_month', Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()),
            'last_month' => new DashboardDateRange(
                'last_month',
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ),
            'custom' => new DashboardDateRange(
                'custom',
                Carbon::parse((string) $from)->startOfDay(),
                Carbon::parse((string) $to)->endOfDay(),
            ),
            default => new DashboardDateRange('last_30_days', Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()),
        };
    }
}
