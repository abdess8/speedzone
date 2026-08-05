<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Head-count per status for a list screen.
 *
 * The cards above a table only mean something if they answer the same question
 * the table does, so the count is taken from the very query that feeds the
 * rows — permission scoping, seller filters and date ranges included. Only the
 * status filter is lifted, otherwise picking one card would zero the others.
 */
final class StatusCounts
{
    /**
     * @param  Builder<*>  $scoped  the list query, built without its status filter
     * @param  array<int, array{value: string, label: string, color: string, icon?: string}>  $statuses
     * @param  array<int, string>  $only  restrict the cards to these statuses, in this order
     * @return array<int, array{value: string, label: string, color: string, icon: string|null, count: int}>
     */
    public static function build(
        Builder $scoped,
        array $statuses,
        string $column = 'status',
        array $only = [],
    ): array {
        // `reorder()` because grouping while ordering on an ungrouped column is
        // rejected outright by MySQL in its default strict mode.
        $counts = (clone $scoped)
            ->reorder()
            ->select([DB::raw($column.' as status_value'), DB::raw('count(*) as aggregate')])
            ->groupBy($column)
            ->pluck('aggregate', 'status_value')
            ->all();

        $order = $only === [] ? array_column($statuses, 'value') : $only;
        $indexed = [];

        foreach ($statuses as $status) {
            $indexed[$status['value']] = $status;
        }

        $cards = [];

        foreach ($order as $value) {
            $status = $indexed[$value] ?? null;

            if ($status === null) {
                continue;
            }

            $cards[] = [
                'value' => $status['value'],
                'label' => $status['label'],
                'color' => $status['color'],
                'icon' => $status['icon'] ?? null,
                'count' => (int) ($counts[$status['value']] ?? 0),
            ];
        }

        return $cards;
    }
}
