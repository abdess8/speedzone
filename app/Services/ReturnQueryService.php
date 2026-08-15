<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use App\Models\User;
use App\Support\SortableQuery;
use App\Support\StatusCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnQueryService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_SORT = 'created_at';

    /** Mirrors {@see User::getFullNameAttribute()} so the order matches the screen. */
    private const USER_FULL_NAME = "coalesce(nullif(concat_ws(' ', users.first_name, users.last_name), ''), users.name)";

    public function build(Request $request, User $user, bool $withStatusFilter = true): Builder
    {
        $query = OrderReturn::query()
            ->with([
                'order.seller.roles',
                'order.city',
                'currentLocationCity',
                'createdBy.roles',
                'assignedDriver',
            ]);

        if ($user->hasPermission('returns.read.all')) {
            // full access
        } elseif ($user->hasPermission('returns.read.own')) {
            $query->ownedBySeller($user->accountOwnerId());
        } elseif ($user->canUpdateReturnStatus() || $user->hasPermission('returns.create')) {
            // Field agents see the returns they opened, plus the ones sitting in
            // a status their own permissions let them advance.
            $query->where(function (Builder $q) use ($user): void {
                $q->where('created_by', $user->id)
                    ->orWhereIn('status', $this->processableStatuses($user));
            });
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
     * Columns the return list may be ordered on.
     *
     * Everything borrowed from the order or from a user is read through a
     * correlated subquery rather than a join: the filters above name `status`,
     * `reason` and `created_at` unqualified, and both `orders` and `users` carry
     * columns of the same name — in this query and in the head-count that
     * reuses it.
     *
     * @return array<string, string|array<int, mixed>>
     */
    private static function sortable(): array
    {
        return [
            'reference' => 'reference',
            'order_tracking' => [
                DB::table('orders')
                    ->select('tracking_number')
                    ->whereColumn('orders.id', 'returns.order_id'),
            ],
            // Same fallback as the resource: the corrected name when the desk
            // has entered one, the customer on the original order otherwise.
            'customer' => [
                DB::raw(
                    "coalesce(nullif(returns.updated_customer_name, ''), (".
                    "select concat_ws(' ', orders.customer_first_name, orders.customer_last_name) ".
                    'from orders where orders.id = returns.order_id))'
                ),
            ],
            'seller' => [
                DB::table('orders')
                    ->join('users', 'users.id', '=', 'orders.seller_id')
                    ->select(DB::raw(self::USER_FULL_NAME))
                    ->whereColumn('orders.id', 'returns.order_id'),
            ],
            'reason' => 'reason',
            'status' => 'status',
            'driver' => [
                DB::table('users')
                    ->select(DB::raw(self::USER_FULL_NAME))
                    ->whereColumn('users.id', 'returns.assigned_to'),
            ],
            'current_city' => [
                DB::table('cities')
                    ->select('name')
                    ->whereColumn('cities.id', 'returns.current_location_city_id'),
            ],
            'created_at' => 'created_at',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function statusCounts(Request $request, User $user): array
    {
        return StatusCounts::build(
            $this->build($request, $user, withStatusFilter: false),
            ReturnStatus::options(),
            'returns.status',
        );
    }

    /**
     * Statuses this user can personally move forward, i.e. the ones whose next
     * step he holds a permission for. A driver therefore sees the returns
     * waiting at the vendor hub but not those still sitting in a manifest.
     *
     * @return array<int, string>
     */
    private function processableStatuses(User $user): array
    {
        $pipeline = ReturnStatus::pipeline();
        $statuses = [];

        foreach ($pipeline as $index => $status) {
            $next = $pipeline[$index + 1] ?? null;

            if ($next === null) {
                continue;
            }

            foreach ($next->allowedBy() as $permission) {
                if ($user->hasPermission($permission)) {
                    $statuses[] = $status->value;

                    break;
                }
            }
        }

        return $statuses;
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
        $query->when($request->filled('search'), fn (Builder $q) => $q->where(function (Builder $inner) use ($request): void {
            $search = $request->string('search')->toString();
            $inner->where('reference', 'like', '%'.$search.'%')
                ->orWhere('updated_customer_name', 'like', '%'.$search.'%')
                ->orWhere('updated_customer_phone', 'like', '%'.$search.'%')
                ->orWhereHas('order', function (Builder $oq) use ($search): void {
                    $oq->where('tracking_number', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhereRaw("CONCAT(customer_first_name, ' ', customer_last_name) like ?", ['%'.$search.'%']);
                });
        }));

        // Accepts a list as well as a single value: the bulk editor narrows the
        // board to the several source statuses that lead to the chosen target.
        $query->when(
            $withStatusFilter && $request->filled('status'),
            function (Builder $q) use ($request): void {
                $statuses = array_filter((array) $request->input('status'), 'strlen');

                count($statuses) === 1
                    ? $q->where('status', reset($statuses))
                    : $q->whereIn('status', $statuses);
            }
        );

        $query->when($request->filled('city_id'), fn (Builder $q) => $q->where(
            'current_location_city_id',
            $request->integer('city_id')
        ));

        $query->when($request->filled('reason'), fn (Builder $q) => $q->where('reason', $request->string('reason')));

        $query->when($request->filled('seller_id'), fn (Builder $q) => $q->whereHas(
            'order',
            fn (Builder $oq) => $oq->where('seller_id', $request->integer('seller_id'))
        ));

        $query->when($request->filled('created_from'), fn (Builder $q) => $q->whereDate(
            'created_at',
            '>=',
            $request->input('created_from')
        ));

        $query->when($request->filled('created_to'), fn (Builder $q) => $q->whereDate(
            'created_at',
            '<=',
            $request->input('created_to')
        ));
    }
}
