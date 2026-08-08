<?php

namespace App\Services;

use App\Enums\PickupRequestStatus;
use App\Models\PickupRequest;
use App\Models\User;
use App\Support\SortableQuery;
use App\Support\StatusCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PickupRequestQueryService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_SORT = 'created_at';

    /** Mirrors {@see User::getFullNameAttribute()} so the order matches the screen. */
    private const USER_FULL_NAME = "coalesce(nullif(concat_ws(' ', users.first_name, users.last_name), ''), users.name)";

    public function build(Request $request, User $user, bool $withStatusFilter = true): Builder
    {
        $query = PickupRequest::query()
            ->with(['creator.roles', 'assignee.roles'])
            ->withCount('orders');

        if ($user->hasPermission('pickup_requests.read.all')) {
            // no scope restriction
        } elseif ($user->hasPermission('pickup_requests.read.assigned')) {
            $query->assignedTo($user->id);
        } elseif ($user->hasPermission('pickup_requests.read.own')) {
            $query->ownedBy($user->accountOwnerId());
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
     * Columns the pickup list may be ordered on.
     *
     * The seller and the driver are read through a correlated subquery rather
     * than a join: the filters above name `status` and `created_at` unqualified,
     * and `users` carries both — in this query and in the head-count that reuses
     * it.
     *
     * @return array<string, string|array<int, mixed>>
     */
    private static function sortable(): array
    {
        return [
            'reference' => 'reference',
            'seller' => [self::userName('pickup_requests.created_by')],
            'pickup_address' => 'pickup_address',
            'number_of_packages' => 'number_of_packages',
            'total_orders_amount' => 'total_orders_amount',
            'driver' => [self::userName('pickup_requests.assigned_to')],
            'status' => 'status',
            'created_at' => 'created_at',
        ];
    }

    private static function userName(string $foreignKey): QueryBuilder
    {
        return DB::table('users')
            ->select(DB::raw(self::USER_FULL_NAME))
            ->whereColumn('users.id', $foreignKey);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function statusCounts(Request $request, User $user): array
    {
        return StatusCounts::build(
            $this->build($request, $user, withStatusFilter: false),
            PickupRequestStatus::options(),
            'pickup_requests.status',
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

        $query->when($request->filled('seller_id'), fn (Builder $q) => $q->where('created_by', $request->integer('seller_id')));

        $query->when($request->filled('created_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->input('created_from')));

        $query->when($request->filled('created_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->input('created_to')));
    }
}
