<?php

namespace App\Search\Providers;

use App\Enums\PickupRequestStatus;
use App\Models\PickupRequest;
use App\Models\User;
use App\Search\SearchHit;
use App\Services\PickupRequestQueryService;
use Illuminate\Database\Eloquent\Builder;

class PickupRequestSearchProvider extends AbstractSearchProvider
{
    public function __construct(private readonly PickupRequestQueryService $pickups) {}

    public function key(): string
    {
        return 'pickups';
    }

    public function label(): string
    {
        return __('search.objects.pickups');
    }

    public function icon(): string
    {
        return 'ri-truck-line';
    }

    public function availableTo(User $user): bool
    {
        return $this->canAny($user, [
            'pickup_requests.read.all',
            'pickup_requests.read.own',
            'pickup_requests.read.assigned',
        ]);
    }

    public function search(User $user, string $term, int $limit): array
    {
        $like = $this->like($term);

        // "Vendor" is whoever asked for the pickup: the shop when the account
        // has one, otherwise the person behind the request.
        $pickups = $this->pickups
            ->build($this->unfiltered(), $user)
            ->with('store')
            ->where(function (Builder $query) use ($like): void {
                $query->where('reference', 'like', $like)
                    ->orWhereHas('store', fn (Builder $store) => $store->where('name', 'like', $like))
                    ->orWhereHas('creator', fn (Builder $creator) => $creator->where(
                        fn (Builder $sub) => $sub
                            ->where('name', 'like', $like)
                            ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) like ?", [$like])
                    ));
            })
            ->limit($limit)
            ->get();

        return $pickups->map(function (PickupRequest $pickup): SearchHit {
            $status = $pickup->status instanceof PickupRequestStatus
                ? $pickup->status
                : PickupRequestStatus::tryFrom((string) $pickup->status);

            $vendor = $pickup->store?->name ?? $pickup->creator?->full_name;

            return new SearchHit(
                id: $pickup->id,
                title: $pickup->reference,
                subtitle: $vendor,
                url: route('pickup-requests.show', $pickup),
                preview: [
                    __('search.fields.vendor') => $vendor,
                    __('search.fields.address') => $pickup->pickup_address,
                    __('search.fields.packages') => $pickup->number_of_packages !== null
                        ? (string) $pickup->number_of_packages
                        : null,
                    __('search.fields.driver') => $pickup->assignee?->full_name,
                    __('search.fields.created_at') => $this->date($pickup->created_at),
                ],
                badge: $status?->label(),
                badgeColor: $status?->color(),
            );
        })->all();
    }
}
