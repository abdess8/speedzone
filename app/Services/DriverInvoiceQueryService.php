<?php

namespace App\Services;

use App\Enums\DriverInvoiceStatus;
use App\Models\DriverInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DriverInvoiceQueryService
{
    private const SORTABLE = [
        'created_at',
        'invoice_number',
        'total_amount',
        'deliveries_count',
        'status',
        'generated_at',
        'paid_at',
    ];

    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_PAGE_SIZE = 25;

    /**
     * Build the filtered, scoped and sorted driver invoice query.
     */
    public function build(Request $request, User $user): Builder
    {
        $query = DriverInvoice::query()->with(['driver']);

        // Authorization scope: users without read.all only see their own invoices.
        if (! $user->hasPermission('driver_invoices.read.all')) {
            $query->forDriver($user->id);
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
        $query->when($request->input('invoice_number'), fn (Builder $q, $value) => $q->where('invoice_number', 'like', "%{$value}%"));

        $query->when($request->input('driver'), function (Builder $q, $value) {
            if (is_numeric($value)) {
                $q->where('driver_id', (int) $value);

                return;
            }

            $q->whereHas('driver', function (Builder $sub) use ($value) {
                $sub->where('name', 'like', "%{$value}%")
                    ->orWhere('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%");
            });
        });

        $query->when($request->filled('driver_id'), fn (Builder $q) => $q->where('driver_id', $request->integer('driver_id')));

        $query->when($request->input('status'), function (Builder $q, $value) {
            $values = array_values(array_intersect((array) $value, DriverInvoiceStatus::values()));
            if ($values !== []) {
                $q->whereIn('status', $values);
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
