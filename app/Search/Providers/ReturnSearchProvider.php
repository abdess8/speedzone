<?php

namespace App\Search\Providers;

use App\Enums\ReturnStatus;
use App\Models\OrderReturn;
use App\Models\User;
use App\Search\SearchHit;
use App\Services\ReturnQueryService;
use Illuminate\Database\Eloquent\Builder;

class ReturnSearchProvider extends AbstractSearchProvider
{
    public function __construct(private readonly ReturnQueryService $returns) {}

    public function key(): string
    {
        return 'returns';
    }

    public function label(): string
    {
        return __('search.objects.returns');
    }

    public function icon(): string
    {
        return 'ri-arrow-go-back-line';
    }

    public function availableTo(User $user): bool
    {
        return $this->canAny($user, [
            'returns.read.all',
            'returns.read.own',
            'returns.create_request',
            'returns.create',
            'returns.update_status',
            'returns.manage',
        ]);
    }

    public function search(User $user, string $term, int $limit): array
    {
        $like = $this->like($term);

        $returns = $this->returns
            ->build($this->unfiltered(), $user)
            ->where(function (Builder $query) use ($like): void {
                $query->where('reference', 'like', $like)
                    ->orWhereHas('order', fn (Builder $order) => $order->where('tracking_number', 'like', $like));
            })
            ->limit($limit)
            ->get();

        return $returns->map(function (OrderReturn $return): SearchHit {
            $status = $return->status instanceof ReturnStatus ? $return->status : ReturnStatus::tryFrom((string) $return->status);

            return new SearchHit(
                id: $return->id,
                title: $return->reference,
                subtitle: $return->order?->tracking_number,
                url: route('returns.show', $return),
                preview: [
                    __('search.fields.order') => $return->order?->tracking_number,
                    __('search.fields.customer') => $return->order?->customer_full_name,
                    __('search.fields.city') => $return->currentLocationCity?->name ?? $return->order?->city?->name,
                    __('search.fields.seller') => $return->order?->seller?->full_name,
                    __('search.fields.created_at') => $this->date($return->created_at),
                ],
                badge: $status?->label(),
                badgeColor: $status?->color(),
            );
        })->all();
    }
}
