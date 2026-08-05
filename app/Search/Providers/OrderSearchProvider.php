<?php

namespace App\Search\Providers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Search\SearchHit;
use App\Services\OrderQueryService;
use App\Services\PartnerOrderQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Orders, native and partner-ingested alike.
 *
 * The two live in the same table but under different visibility rules — ours by
 * seller or assignment, the partner's by the partners the account is attached
 * to — so they are read through their own list services and merged afterwards.
 * The searcher does not care which pipeline a parcel arrived through; he has a
 * tracking number in his hand.
 */
class OrderSearchProvider extends AbstractSearchProvider
{
    public function __construct(
        private readonly OrderQueryService $orders,
        private readonly PartnerOrderQueryService $partnerOrders,
    ) {}

    public function key(): string
    {
        return 'orders';
    }

    public function label(): string
    {
        return __('search.objects.orders');
    }

    public function icon(): string
    {
        return 'ri-shopping-basket-2-line';
    }

    public function availableTo(User $user): bool
    {
        return $this->canAny($user, ['orders.read.all', 'orders.read.own', 'orders.read.assigned'])
            || $user->canManageAllPartners()
            || $user->partners()->exists();
    }

    public function search(User $user, string $term, int $limit): array
    {
        $orders = $this->native($user, $term, $limit)
            ->concat($this->partner($user, $term, $limit))
            ->unique('id')
            ->sortByDesc('created_at')
            ->take($limit);

        // A read scope limited to what he is carrying gets the list screen, not
        // the detail page: OrderPolicy keeps a field agent out of the seller,
        // billing and history sections, so linking him there is a 403.
        $canOpenDetails = OrderPolicy::grantsDetailAccess($user);

        return $orders->map(function (Order $order) use ($canOpenDetails): SearchHit {
            $status = $order->status instanceof OrderStatus
                ? $order->status
                : OrderStatus::tryFrom((string) $order->status);

            return new SearchHit(
                id: $order->id,
                title: $order->tracking_number,
                subtitle: $order->customer_full_name,
                url: $this->destination($order, $canOpenDetails),
                preview: [
                    __('search.fields.customer') => $order->customer_full_name,
                    __('search.fields.phone') => $order->customer_phone,
                    __('search.fields.address') => $order->customer_address,
                    __('search.fields.city') => $order->city?->name,
                    __('search.fields.amount') => $this->money($order->total_amount),
                    __('search.fields.partner') => $order->partner?->name,
                    __('search.fields.seller') => $order->seller?->full_name,
                    __('search.fields.created_at') => $this->date($order->created_at),
                ],
                badge: $status?->label(),
                badgeColor: $status?->color(),
            );
        })->values()->all();
    }

    /**
     * Starting from the list query rather than from `Order::query()` is what
     * keeps a driver from finding an order that is not his.
     *
     * @return Collection<int, Order>
     */
    private function native(User $user, string $term, int $limit): Collection
    {
        if (! $this->canAny($user, ['orders.read.all', 'orders.read.own', 'orders.read.assigned'])) {
            return collect();
        }

        return $this->matching(
            $this->orders->build($this->unfiltered(), $user, ['city', 'seller']),
            $term
        )->limit($limit)->get();
    }

    /**
     * @return Collection<int, Order>
     */
    private function partner(User $user, string $term, int $limit): Collection
    {
        // The visibility scope already answers "none" for an account with no
        // partner attachment, so this needs no permission gate of its own.
        return $this->matching(
            $this->partnerOrders->build($this->unfiltered(), $user)->with(['city', 'seller', 'partner']),
            $term
        )->limit($limit)->get();
    }

    private function matching(Builder $query, string $term): Builder
    {
        $like = $this->like($term);

        return $query->where(function (Builder $sub) use ($like): void {
            $sub->where('tracking_number', 'like', $like)
                ->orWhere('customer_phone', 'like', $like)
                ->orWhereRaw(
                    "CONCAT_WS(' ', customer_first_name, customer_last_name) like ?",
                    [$like]
                );
        });
    }

    private function destination(Order $order, bool $canOpenDetails): string
    {
        if ($canOpenDetails) {
            return route('orders.show', $order);
        }

        return $order->partner_id
            ? route('partner-orders.index', ['tracking_number' => $order->tracking_number])
            : route('orders.index', ['tracking_number' => $order->tracking_number]);
    }
}
