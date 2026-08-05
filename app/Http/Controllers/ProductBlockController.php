<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Quarantine of a defective reference (stock.admin_override).
 *
 * A blocked product stays in the catalog and stays countable — it is still
 * sitting on our shelf — but the pick-list refuses it, so no new order can be
 * built from goods we have decided not to ship.
 */
class ProductBlockController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function __invoke(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('block', $product);

        $data = $request->validate([
            'blocked' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->productService->setBlocked(
            $product,
            (bool) $data['blocked'],
            $data['reason'] ?? null,
            $request->user()
        );

        return redirect()->back()->with(
            'success',
            $data['blocked']
                ? __('stock.products.flash.blocked')
                : __('stock.products.flash.released')
        );
    }
}
