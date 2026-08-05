<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use App\Models\User;
use App\Support\StatusCounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReturnQueryService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

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

        return $query->orderByDesc('created_at')->orderByDesc('id');
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
                ->orWhereHas('order', fn (Builder $oq) => $oq->where('tracking_number', 'like', '%'.$search.'%'));
        }));

        $query->when(
            $withStatusFilter && $request->filled('status'),
            fn (Builder $q) => $q->where('status', $request->string('status'))
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
