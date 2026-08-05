<?php

namespace App\Search\Providers;

use App\Enums\StockReceptionStatus;
use App\Models\StockReception;
use App\Models\User;
use App\Search\SearchHit;
use Illuminate\Database\Eloquent\Builder;

class StockReceptionSearchProvider extends AbstractSearchProvider
{
    public function key(): string
    {
        return 'stock-receptions';
    }

    public function label(): string
    {
        return __('search.objects.stock_receptions');
    }

    public function icon(): string
    {
        return 'ri-inbox-archive-line';
    }

    public function availableTo(User $user): bool
    {
        return $this->canAny($user, [
            'stock.view',
            'stock.create_inbound',
            'stock.collect_inbound',
            'stock.receive_inbound',
            'stock.admin_override',
        ]);
    }

    public function search(User $user, string $term, int $limit): array
    {
        $like = $this->like($term);

        $receptions = StockReception::query()
            ->with(['seller', 'destinationCity'])
            ->withCount('items')
            ->where(function (Builder $query) use ($like): void {
                $query->where('reference', 'like', $like)
                    ->orWhereHas('items.product', fn (Builder $product) => $product->where(
                        fn (Builder $sub) => $sub
                            ->where('name', 'like', $like)
                            ->orWhere('sku', 'like', $like)
                    ));
            })
            ->orderByDesc('created_at')
            ->limit($this->overfetch($limit))
            ->get();

        return collect($this->readable($receptions, $user, $limit))->map(function (StockReception $reception): SearchHit {
            $status = $reception->status instanceof StockReceptionStatus
                ? $reception->status
                : StockReceptionStatus::tryFrom((string) $reception->status);

            return new SearchHit(
                id: $reception->id,
                title: $reception->reference,
                subtitle: $reception->seller?->full_name,
                url: route('stock-receptions.show', $reception),
                preview: [
                    __('search.fields.seller') => $reception->seller?->full_name,
                    __('search.fields.destination') => $reception->destinationCity?->name,
                    __('search.fields.lines_count') => (string) $reception->items_count,
                    __('search.fields.created_at') => $this->date($reception->created_at),
                ],
                badge: $status?->label(),
                badgeColor: $status?->color(),
            );
        })->all();
    }
}
