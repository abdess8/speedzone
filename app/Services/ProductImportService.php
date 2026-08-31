<?php

namespace App\Services;

use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductImportService
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly StockLedgerService $ledger,
    ) {}

    /**
     * Create every product of a validated batch.
     *
     * The whole batch is one transaction, for the same reason the order import is:
     * the rows were checked together on screen and again by ImportProductsRequest,
     * so a failure here is a system error rather than a bad row, and a seller who
     * uploaded three hundred references should not have to work out which half of
     * them exist.
     *
     * @param  array<int, array<string, mixed>>  $rows  Validated product payloads.
     * @return Collection<int, Product>
     */
    public function import(array $rows, User $actor): Collection
    {
        return DB::transaction(function () use ($rows, $actor): Collection {
            $products = collect();

            foreach ($rows as $row) {
                $openingStock = (int) ($row['stock_quantity'] ?? 0);
                unset($row['stock_quantity']);

                $product = $this->productService->create($row, $actor);

                // Opening stock goes through the ledger like everything else, so
                // a product that starts at 40 units can still explain where they
                // came from six months later.
                if ($openingStock > 0) {
                    $this->ledger->setQuantity(
                        product: $product,
                        countedQuantity: $openingStock,
                        actor: $actor,
                        reason: StockAdjustmentReason::INITIAL_STOCK,
                        note: __('stock.products.import.opening_stock_note'),
                    );
                }

                $products->push($product->refresh());
            }

            return $products;
        });
    }
}
