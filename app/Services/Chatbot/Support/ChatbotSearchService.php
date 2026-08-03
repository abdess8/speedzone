<?php

namespace App\Services\Chatbot\Support;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Read-only search behind the assistant's `searchEntities` tool.
 *
 * Order visibility reuses {@see DashboardService::scopedOrdersQuery()} so the
 * assistant and the dashboard can never disagree on what a user may see, and
 * people lookups stay behind `users.read`.
 */
class ChatbotSearchService
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function run(User $user, string $query, string $type = 'all', int $limit = 5): array
    {
        $term = Str::of($query)->trim()->ltrim('#')->squish()->value();
        $limit = max(1, min($limit, 20));

        $wanted = fn (string $entity): bool => $type === 'all' || $type === $entity;

        return array_filter([
            'orders' => $wanted('orders') ? $this->orders($user, $term, $limit) : [],
            'drivers' => $wanted('drivers') ? $this->people($user, $term, $limit, Role::DRIVER) : [],
            'sellers' => $wanted('sellers') ? $this->people($user, $term, $limit, Role::SELLER) : [],
            'customers' => $wanted('customers') ? $this->customers($user, $term, $limit) : [],
        ], fn (array $rows) => $rows !== []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orders(User $user, string $term, int $limit): array
    {
        $like = '%'.$this->escapeLike($term).'%';

        return $this->dashboard->scopedOrdersQuery($user)
            ->with(['city', 'driver:id,name', 'seller:id,name'])
            ->where(function (Builder $q) use ($like, $term) {
                $q->where('tracking_number', 'like', $like)
                    ->orWhere('external_tracking_code', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhere('customer_first_name', 'like', $like)
                    ->orWhere('customer_last_name', 'like', $like)
                    ->orWhereRaw("CONCAT(customer_first_name, ' ', customer_last_name) LIKE ?", [$like])
                    ->orWhereHas('city', fn (Builder $c) => $c->where('name', 'like', $like));

                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => OrderLocator::summarise($order, $user))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function people(User $user, string $term, int $limit, string $role): array
    {
        // Staff directory lookups are a separate privilege from reading orders.
        if (! $user->hasPermission('users.read')) {
            return [];
        }

        $like = '%'.$this->escapeLike($term).'%';

        return User::query()
            ->select(['id', 'name', 'email', 'phone_number', 'city_id', 'status'])
            ->with('city:id,name')
            ->whereHas('roles', fn (Builder $q) => $q->where('name', $role))
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone_number', 'like', $like))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'phone' => $person->phone_number,
                'city' => $person->city?->name,
                'role' => $role,
            ])
            ->all();
    }

    /**
     * Customers are denormalised onto orders, so they are searched — and
     * aggregated — through the same scoped order query.
     *
     * @return array<int, array<string, mixed>>
     */
    private function customers(User $user, string $term, int $limit): array
    {
        $like = '%'.$this->escapeLike($term).'%';

        return $this->dashboard->scopedOrdersQuery($user)
            ->selectRaw('customer_phone')
            ->selectRaw('MAX(customer_first_name) as first_name, MAX(customer_last_name) as last_name')
            ->selectRaw('COUNT(*) as orders_count')
            ->where(fn (Builder $q) => $q
                ->where('customer_phone', 'like', $like)
                ->orWhere('customer_first_name', 'like', $like)
                ->orWhere('customer_last_name', 'like', $like)
                ->orWhereRaw("CONCAT(customer_first_name, ' ', customer_last_name) LIKE ?", [$like]))
            ->groupBy('customer_phone')
            ->orderByDesc('orders_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => trim("{$row->first_name} {$row->last_name}"),
                'phone' => $row->customer_phone,
                'orders' => (int) $row->orders_count,
            ])
            ->all();
    }

    /**
     * The model composes the term from user text, so LIKE wildcards it happens
     * to forward must match literally instead of widening the scan.
     */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }
}
