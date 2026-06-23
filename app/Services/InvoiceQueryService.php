<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InvoiceQueryService
{
    private const SORTABLE = [
        'created_at',
        'invoice_number',
        'net_amount',
        'gross_amount',
        'total_orders_count',
        'status',
        'generated_at',
        'paid_at',
    ];

    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_PAGE_SIZE = 25;

    /**
     * Build the filtered, scoped and sorted invoice query.
     */
    public function build(Request $request, User $user): Builder
    {
        $query = Invoice::query()->with(['seller']);

        // Authorization scope: users without read.all only see their own invoices.
        if (! $user->hasPermission('invoices.read.all')) {
            $query->forSeller($user->id);
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

        // Seller by id or name.
        $query->when($request->input('seller'), function (Builder $q, $value) {
            if (is_numeric($value)) {
                $q->where('seller_id', (int) $value);

                return;
            }

            $q->whereHas('seller', function (Builder $sub) use ($value) {
                $sub->where('name', 'like', "%{$value}%")
                    ->orWhere('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%");
            });
        });

        $query->when($request->filled('seller_id'), fn (Builder $q) => $q->where('seller_id', $request->integer('seller_id')));

        $query->when($request->input('status'), function (Builder $q, $value) {
            $values = array_values(array_intersect((array) $value, InvoiceStatus::values()));
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
