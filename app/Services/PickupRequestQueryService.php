<?php

namespace App\Services;

use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PickupRequestQueryService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    public function build(Request $request, User $user): Builder
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

        $this->applyFilters($query, $request);

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PAGE_SIZE);

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PAGE_SIZE;
        }

        return min($perPage, self::MAX_PAGE_SIZE);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), fn (Builder $q) => $q->where(
            'reference',
            'like',
            '%'.$request->string('search').'%'
        ));

        $query->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')));

        $query->when($request->filled('seller_id'), fn (Builder $q) => $q->where('created_by', $request->integer('seller_id')));

        $query->when($request->filled('created_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->input('created_from')));

        $query->when($request->filled('created_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->input('created_to')));
    }
}
