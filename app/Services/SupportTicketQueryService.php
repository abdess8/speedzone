<?php

namespace App\Services;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SupportPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SupportTicketQueryService
{
    private const SORTABLE = [
        'created_at',
        'reference',
        'status',
        'category',
        'last_reply_at',
    ];

    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_PAGE_SIZE = 25;

    /**
     * Build the filtered, scoped and sorted support ticket query.
     */
    public function build(Request $request, User $user): Builder
    {
        $query = SupportTicket::query()->with(['creator', 'assignee']);

        // Sellers / agents without read.all only see their own or assigned tickets.
        if (! $user->hasPermission(SupportPermissions::READ_ALL) && ! $user->hasPermission(SupportPermissions::MANAGE)) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->accountOwnerId())
                    ->orWhere('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query;
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
        $query->when($request->input('reference'), fn (Builder $q, $value) => $q->where('reference', 'like', "%{$value}%"));

        $query->when($request->input('subject'), fn (Builder $q, $value) => $q->where('subject', 'like', "%{$value}%"));

        $query->when($request->input('seller'), function (Builder $q, $value) {
            if (is_numeric($value)) {
                $q->where('created_by', (int) $value);

                return;
            }

            $q->whereHas('creator', function (Builder $sub) use ($value) {
                $sub->where('name', 'like', "%{$value}%")
                    ->orWhere('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%");
            });
        });

        $query->when($request->filled('assigned_to'), function (Builder $q) use ($request) {
            $value = $request->input('assigned_to');
            if ($value === 'unassigned') {
                $q->whereNull('assigned_to');

                return;
            }
            $q->where('assigned_to', (int) $value);
        });

        $query->when($request->input('status'), function (Builder $q, $value) {
            $values = array_values(array_intersect((array) $value, SupportTicketStatus::values()));
            if ($values !== []) {
                $q->whereIn('status', $values);
            }
        });

        $query->when($request->input('category'), function (Builder $q, $value) {
            $values = array_values(array_intersect((array) $value, SupportTicketCategory::values()));
            if ($values !== []) {
                $q->whereIn('category', $values);
            }
        });

        $query->when($request->input('created_from'), fn (Builder $q, $value) => $q->whereDate('created_at', '>=', $value));
        $query->when($request->input('created_to'), fn (Builder $q, $value) => $q->whereDate('created_at', '<=', $value));
    }

    private function applySorting(Builder $query, Request $request): void
    {
        $sort = (string) $request->input('sort', 'created_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $direction)->orderBy('id', 'desc');
    }
}
