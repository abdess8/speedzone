<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PartnerOrderQueryService
{
    private const SORTABLE = [
        'created_at',
        'tracking_number',
        'order_amount',
        'order_value',
        'delivery_price',
        'status',
    ];

    private const MAX_PAGE_SIZE = 100;

    private const DEFAULT_PAGE_SIZE = 25;

    public function build(Request $request, User $user): Builder
    {
        $query = Order::query()
            ->with(['city', 'sector', 'partner', 'driver'])
            ->visibleForPartnerDeliveryAccess($user);

        $this->applyFilters($query, $request, $user);
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

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function partnerOptions(User $user): array
    {
        $query = Partner::query()->active()->orderBy('name');

        if (! $user->canManageAllPartners()) {
            $query->whereIn('id', $user->partners()->select('partners.id'));
        }

        return $query
            ->get(['id', 'name'])
            ->map(fn (Partner $partner) => ['id' => $partner->id, 'name' => $partner->name])
            ->all();
    }

    private function applyFilters(Builder $query, Request $request, User $user): void
    {
        $query->when($request->filled('partner_id'), fn (Builder $q) => $q->where('partner_id', $request->integer('partner_id')));

        $tracking = $request->input('tracking_number') ?? $request->input('order_number');
        $query->when($tracking, function (Builder $q, $value) {
            $q->where(function (Builder $sub) use ($value) {
                $sub->where('tracking_number', 'like', "%{$value}%")
                    ->orWhere('external_tracking_code', 'like', "%{$value}%");
            });
        });

        $query->when($request->input('customer_name'), function (Builder $q, $value) {
            $q->where(function (Builder $sub) use ($value) {
                $sub->where('customer_first_name', 'like', "%{$value}%")
                    ->orWhere('customer_last_name', 'like', "%{$value}%")
                    ->orWhereRaw("CONCAT(customer_first_name, ' ', customer_last_name) like ?", ["%{$value}%"]);
            });
        });

        $query->when($request->input('customer_phone'), fn (Builder $q, $value) => $q->where('customer_phone', 'like', "%{$value}%"));
        $query->when($request->filled('city_id'), fn (Builder $q) => $q->where('city_id', $request->integer('city_id')));

        $query->when($request->input('status'), function (Builder $q, $value) {
            $values = array_values(array_intersect((array) $value, OrderStatus::values()));
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
