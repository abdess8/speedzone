<?php

namespace App\Search\Providers;

use App\Models\Product;
use App\Models\User;
use App\Search\SearchHit;

/**
 * The same products as the product catalogue, but landing on the inventory
 * screen with the line already filtered in — that is where the quantity is
 * corrected, and it is the reason someone searches stock by name.
 */
class InventorySearchProvider extends AbstractSearchProvider
{
    public function key(): string
    {
        return 'inventory';
    }

    public function label(): string
    {
        return __('search.objects.inventory');
    }

    public function icon(): string
    {
        return 'ri-stack-line';
    }

    public function availableTo(User $user): bool
    {
        return $this->canAny($user, ['stock.view', 'stock.admin_override']);
    }

    public function search(User $user, string $term, int $limit): array
    {
        $products = Product::query()
            ->search($term)
            ->orderBy('name')
            ->limit($this->overfetch($limit))
            ->get();

        return collect($this->readable($products, $user, $limit))->map(fn (Product $product): SearchHit => new SearchHit(
            id: $product->id,
            title: $product->name,
            subtitle: $product->sku,
            url: route('stock.inventory', ['search' => $product->sku ?: $product->name]),
            preview: [
                __('search.fields.sku') => $product->sku,
                __('search.fields.stock_quantity') => (string) $product->stock_quantity,
                __('search.fields.unit_price') => $this->money($product->unit_price),
                __('search.fields.category') => $product->category,
            ],
            badge: (int) $product->stock_quantity > 0
                ? __('search.badges.in_stock')
                : __('search.badges.out_of_stock'),
            badgeColor: (int) $product->stock_quantity > 0 ? 'success' : 'danger',
        ))->all();
    }
}
