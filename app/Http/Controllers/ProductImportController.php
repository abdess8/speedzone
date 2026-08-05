<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportProductsRequest;
use App\Models\Product;
use App\Services\ProductImportService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bulk creation of catalog references from a CSV/Excel file.
 *
 * Same division of labour as the order import: parsing, column mapping and row
 * repair all happen in the browser, and this controller only serves the
 * reference data the wizard needs before re-validating the finished batch.
 */
class ProductImportController extends Controller
{
    public function __construct(
        private readonly ProductImportService $importService,
        private readonly ProductService $productService,
    ) {}

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('stock/products/import', [
            // Existing categories drive the suggestions in the review table, so a
            // file that spells "T-shirts" three ways can be normalised on screen.
            'categories' => $this->productService->categories(),
            'maxRows' => (int) config('stock.import_max_rows', 1000),
        ]);
    }

    public function store(ImportProductsRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $products = $this->importService->import($request->rows(), $request->user());

        return redirect()
            ->route('products.index')
            ->with('success', __('stock.products.import.flash.created', ['count' => $products->count()]));
    }
}
