<?php

namespace App\Search\Providers;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use App\Search\SearchHit;
use App\Services\TransferQueryService;
use Illuminate\Database\Eloquent\Builder;

class TransferSearchProvider extends AbstractSearchProvider
{
    public function __construct(private readonly TransferQueryService $transfers) {}

    public function key(): string
    {
        return 'transfers';
    }

    public function label(): string
    {
        return __('search.objects.transfers');
    }

    public function icon(): string
    {
        return 'ri-route-line';
    }

    public function availableTo(User $user): bool
    {
        return $this->canAny($user, ['transfers.read', 'transfers.read.assigned']);
    }

    public function search(User $user, string $term, int $limit): array
    {
        $like = $this->like($term);

        $transfers = $this->transfers
            ->build($this->unfiltered(), $user)
            ->where(function (Builder $query) use ($like): void {
                $query->where('reference', 'like', $like)
                    ->orWhereHas('fromCity', fn (Builder $city) => $city->where('name', 'like', $like));
            })
            ->limit($limit)
            ->get();

        return $transfers->map(function (Transfer $transfer): SearchHit {
            $status = $transfer->status instanceof TransferStatus
                ? $transfer->status
                : TransferStatus::tryFrom((string) $transfer->status);

            $route = trim(($transfer->fromCity?->name ?? '').' → '.($transfer->toCity?->name ?? ''), ' →');

            return new SearchHit(
                id: $transfer->id,
                title: $transfer->reference,
                subtitle: $route !== '' ? $route : null,
                url: route('transfers.show', $transfer),
                preview: [
                    __('search.fields.from_city') => $transfer->fromCity?->name,
                    __('search.fields.to_city') => $transfer->toCity?->name,
                    __('search.fields.packages') => $transfer->number_of_packages !== null
                        ? (string) $transfer->number_of_packages
                        : null,
                    __('search.fields.driver') => $transfer->assignee?->full_name,
                    __('search.fields.created_at') => $this->date($transfer->created_at),
                ],
                badge: $status?->label(),
                badgeColor: $status?->color(),
            );
        })->all();
    }
}
