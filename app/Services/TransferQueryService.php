<?php

namespace App\Services;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TransferQueryService
{
    private const DEFAULT_PAGE_SIZE = 15;

    private const MAX_PAGE_SIZE = 100;

    public function build(Request $request, User $user): Builder
    {
        $query = Transfer::query()
            ->with(['fromCity', 'toCity', 'creator', 'assignee'])
            ->withCount('orders');

        if ($user->hasPermission('transfers.read')) {
            // full access
        } elseif ($user->hasPermission('transfers.read.assigned')) {
            $query->assignedTo($user->id);
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

        $query->when($request->filled('from_city_id'), fn (Builder $q) => $q->where('from_city_id', $request->integer('from_city_id')));

        $query->when($request->filled('to_city_id'), fn (Builder $q) => $q->where('to_city_id', $request->integer('to_city_id')));

        $query->when($request->filled('created_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->input('created_from')));

        $query->when($request->filled('created_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->input('created_to')));
    }
}
