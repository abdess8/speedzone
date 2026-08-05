<?php

namespace App\Search\Providers;

use App\Models\Product;
use App\Models\User;
use App\Search\SearchHit;

class ProductSearchProvider extends AbstractSearchProvider
{
    public function key(): string
    {
        return 'products';
    }

    public function label(): string
    {
        return __('search.objects.products');
    }

    public function icon(): string
    {
        return 'ri-price-tag-3-line';
    }

    public function availableTo(User $user): bool
    {
        return $this->canAny($user, ['stock.view', 'stock.receive_inbound', 'stock.admin_override']);
    }

    public function search(User $user, string $term, int $limit): array
    {
        // `scopeSearch` covers sku, name and barcode, and the store scope on the
        // model keeps a vendor inside the shop he is standing on.
        $products = Product::query()
            ->with('store')
            ->search($term)
            ->orderBy('name')
            ->limit($this->overfetch($limit))
            ->get();

        return collect($this->readable($products, $user, $limit))->map(fn (Product $product): SearchHit => new SearchHit(
            id: $product->id,
            title: $product->name,
            subtitle: $product->sku,
            url: route('products.show', $product),
            preview: [
                __('search.fields.sku') => $product->sku,
                __('search.fields.category') => $product->category,
                __('search.fields.unit_price') => $this->money($product->unit_price),
                __('search.fields.stock_quantity') => (string) $product->stock_quantity,
                __('search.fields.shop') => $product->store?->name,
            ],
            badge: $product->is_active ? __('common.active') : __('common.inactive'),
            badgeColor: $product->is_active ? 'success' : 'secondary',
        ))->all();
    }
}
