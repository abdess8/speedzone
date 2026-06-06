<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderQueryService
{
    private const SORTABLE = [
        'created_at',
        'tracking_number',
        'order_amount',
        'delivery_price',
        'status',
    ];

    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_PAGE_SIZE = 25;

    /**
     * Build the filtered, scoped and sorted order query.
     */
    public function build(Request $request, User $user): Builder
    {
        $query = Order::query()->with(['city', 'sector', 'seller']);

        // Authorization scope: users without read.all only see their own orders.
        if (! $user->hasPermission('orders.read.all')) {
            $query->ownedBy($user->id);
        }

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        return $query;
    }

    /**
     * Resolve the requested (and bounded) page size.
     */
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
        // Tracking number / order number (same field).
        $tracking = $request->input('tracking_number') ?? $request->input('order_number');
        $query->when($tracking, fn (Builder $q, $value) => $q->where('tracking_number', 'like', "%{$value}%"));

        $query->when($request->input('customer_name'), function (Builder $q, $value) {
            $q->where(function (Builder $sub) use ($value) {
                $sub->where('customer_first_name', 'like', "%{$value}%")
                    ->orWhere('customer_last_name', 'like', "%{$value}%")
                    ->orWhereRaw("CONCAT(customer_first_name, ' ', customer_last_name) like ?", ["%{$value}%"]);
            });
        });

        $query->when($request->input('customer_phone'), fn (Builder $q, $value) => $q->where('customer_phone', 'like', "%{$value}%"));

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
        $query->when($request->filled('city_id'), fn (Builder $q) => $q->where('city_id', $request->integer('city_id')));
        $query->when($request->filled('sector_id'), fn (Builder $q) => $q->where('sector_id', $request->integer('sector_id')));

        $query->when($request->input('status'), function (Builder $q, $value) {
            $values = array_values(array_intersect((array) $value, OrderStatus::values()));
            if ($values !== []) {
                $q->whereIn('status', $values);
            }
        });

        $query->when($request->input('payment_method'), fn (Builder $q, $value) => $q->whereIn('payment_method', (array) $value));

        // Creation date range.
        $query->when($request->input('created_from'), fn (Builder $q, $value) => $q->whereDate('created_at', '>=', $value));
        $query->when($request->input('created_to'), fn (Builder $q, $value) => $q->whereDate('created_at', '<=', $value));

        // Delivery date range — based on when the order reached the DELIVERED status.
        if ($request->filled('delivery_from') || $request->filled('delivery_to')) {
            $query->whereHas('statusHistories', function (Builder $sub) use ($request) {
                $sub->where('status', OrderStatus::DELIVERED->value);
                $sub->when($request->input('delivery_from'), fn (Builder $s, $value) => $s->whereDate('created_at', '>=', $value));
                $sub->when($request->input('delivery_to'), fn (Builder $s, $value) => $s->whereDate('created_at', '<=', $value));
            });
        }

        // Package flags (only filter when explicitly provided).
        if ($request->filled('is_fragile')) {
            $query->where('is_fragile', $request->boolean('is_fragile'));
        }

        if ($request->filled('can_be_opened')) {
            $query->where('can_be_opened', $request->boolean('can_be_opened'));
        }
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
