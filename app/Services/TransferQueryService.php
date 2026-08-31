<?php

namespace App\Services;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use App\Support\SortableQuery;
use App\Support\StatusCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferQueryService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_SORT = 'created_at';

    public function build(Request $request, User $user, bool $withStatusFilter = true): Builder
    {
        $query = Transfer::query()
            ->with(['fromCity', 'toCity', 'creator.roles', 'assignee.roles'])
            ->withCount('orders');

        if ($user->hasPermission('transfers.read')) {
            // full access
        } elseif ($user->hasPermission('transfers.read.assigned')) {
            $query->assignedTo($user->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        $this->applyFilters($query, $request, $withStatusFilter);

        SortableQuery::apply($query, $request, self::sortable(), self::DEFAULT_SORT);

        return $query;
    }

    /**
     * The order actually applied, echoed back so the header can draw its arrow.
     *
     * @return array{sort: string, direction: string}
     */
    public function sortState(Request $request): array
    {
        return SortableQuery::state($request, self::sortable(), self::DEFAULT_SORT);
    }

    /**
     * Columns the transfer list may be ordered on.
     *
     * The two cities are read through a correlated subquery rather than a join:
     * the filters above name `created_at` and `status` unqualified, and joining
     * `cities` — which carries a `created_at` of its own — would make them
     * ambiguous, in this query and in the head-count that reuses it.
     *
     * @return array<string, string|array<int, mixed>>
     */
    private static function sortable(): array
    {
        return [
            'reference' => 'reference',
            'from_city' => [self::cityName('transfers.from_city_id')],
            'to_city' => [self::cityName('transfers.to_city_id')],
            'number_of_packages' => 'number_of_packages',
            'total_amount' => 'total_amount',
            'status' => 'status',
            'created_at' => 'created_at',
        ];
    }

    private static function cityName(string $foreignKey): QueryBuilder
    {
        return DB::table('cities')->select('name')->whereColumn('cities.id', $foreignKey);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function statusCounts(Request $request, User $user): array
    {
        return StatusCounts::build(
            $this->build($request, $user, withStatusFilter: false),
            TransferStatus::options(),
            'transfers.status',
        );
    }

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
        $query->when($request->filled('search'), fn (Builder $q) => $q->where(
            'reference',
            'like',
            '%'.$request->string('search').'%'
        ));

        $query->when(
            $withStatusFilter && $request->filled('status'),
            fn (Builder $q) => $q->where('status', $request->string('status'))
        );

        $query->when($request->filled('from_city_id'), fn (Builder $q) => $q->where('from_city_id', $request->integer('from_city_id')));

        $query->when($request->filled('to_city_id'), fn (Builder $q) => $q->where('to_city_id', $request->integer('to_city_id')));

        $query->when($request->filled('created_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->input('created_from')));

        $query->when($request->filled('created_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->input('created_to')));
    }
}
