<?php

namespace App\Services\Chatbot\Tools;

use App\Models\User;
use App\Services\Chatbot\Support\KpiTimeframe;
use App\Services\Chatbot\ToolResult;
use App\Services\DashboardService;
use Illuminate\Validation\Rule;

/**
 * Answers KPI questions ("what is the delivery success rate this week?").
 *
 * The figures come straight from {@see DashboardService}, the same cached
 * payload the dashboard renders, so the assistant can never quote a number the
 * user cannot see on screen.
 */
class GetDeliveryKpisTool extends AbstractChatbotTool
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function name(): string
    {
        return 'getDeliveryKpis';
    }

    public function description(): string
    {
        return 'Read delivery and revenue KPIs over a timeframe: success rate, delivered / '
            .'failed / returned counts, average delivery time, revenue, cash to collect, and '
            .'the top performing drivers and cities. Call this for any question about volume, '
            .'performance or money over a period.';
    }

    public function parameters(): array
    {
        return $this->schema([
            'timeframe' => [
                'type' => 'string',
                'enum' => KpiTimeframe::values(),
                'description' => 'Period to report on. Use "custom" together with from/to for anything else.',
            ],
            'from' => [
                'type' => 'string',
                'description' => 'Start date (YYYY-MM-DD), required when timeframe is "custom".',
            ],
            'to' => [
                'type' => 'string',
                'description' => 'End date (YYYY-MM-DD), required when timeframe is "custom".',
            ],
        ], ['timeframe']);
    }

    public function isAvailableFor(User $user): bool
    {
        return $user->hasPermission('orders.read.all')
            || $user->hasPermission('orders.read.own')
            || $user->hasPermission('orders.read.assigned');
    }

    public function execute(array $arguments, User $user): ToolResult
    {
        $input = $this->validate($arguments, [
            'timeframe' => ['required', 'string', Rule::in(KpiTimeframe::values())],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:timeframe,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:timeframe,custom', 'after_or_equal:from'],
        ]);

        $range = KpiTimeframe::resolve(
            $input['timeframe'],
            $input['from'] ?? null,
            $input['to'] ?? null,
        );

        $data = $this->dashboard->get($user, $range);
        $summary = $data['summary'];
        $performance = $data['deliveryPerformance'];

        $metrics = [
            'orders_in_period' => $summary['orders_in_period'],
            'delivered_orders' => $summary['delivered_orders'],
            'failed_deliveries' => $summary['failed_deliveries'],
            'returned_orders' => $summary['returned_orders'],
            'cancelled_orders' => $summary['cancelled_orders'],
            'in_transit' => $summary['in_transit'],
            'out_for_delivery' => $summary['out_for_delivery'],
            'pending_pickup' => $summary['pending_pickup'],
            'delivery_success_rate' => $summary['delivery_success_rate'],
            'average_delivery_time_hours' => $summary['average_delivery_time_hours'],
            'average_order_value' => $summary['average_order_value'],
            'revenue_in_period' => $summary['revenue_in_period'],
            'cod_collected' => $summary['cod_collected'],
            'cash_to_collect' => $summary['cash_to_collect'],
            'active_delivery_agents' => $summary['active_delivery_agents'],
            'new_customers' => $summary['new_customers'],
        ];

        return ToolResult::success(
            modelPayload: [
                'timeframe' => $data['meta']['filter'],
                'metrics' => $metrics,
                'top_drivers' => array_slice($performance['top_agents'], 0, 3),
                'top_cities' => array_slice($data['topCities'], 0, 3),
            ],
            actionType: 'kpi_report',
            actionData: [
                'timeframe' => $data['meta']['filter'],
                'metrics' => $metrics,
                'top_drivers' => array_slice($performance['top_agents'], 0, 5),
                'top_cities' => array_slice($data['topCities'], 0, 5),
            ],
        );
    }
}
