<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    private const PHOTO_DIRECTORY = 'product-photos';

    public function __construct(
        private readonly SkuGenerator $skuGenerator,
        private readonly ProductAuditService $auditService,
    ) {}

    /**
     * Create a catalog reference on behalf of the authenticated vendor.
     *
     * Ownership goes to the vendor account, so a product keyed in by a team
     * member stays readable by — and billable to — his employer. The store is
     * filled in automatically by BelongsToStore from the active store.
     *
     * @param  array<string, mixed>  $data  Validated product payload.
     */
    public function create(array $data, User $actor, ?UploadedFile $photo = null): Product
    {
        return DB::transaction(function () use ($data, $actor, $photo): Product {
            $product = new Product($data);
            $product->seller_id = $actor->accountOwnerId();

            if ($photo) {
                $product->photo_path = $this->storePhoto($photo);
            }

            if (blank($product->sku)) {
                // The store is resolved here rather than left to BelongsToStore,
                // which only fills it in on the way to the INSERT: references are
                // unique per store, so the generator needs it beforehand.
                $product->store_id ??= app(StoreContext::class)->id();
                $product->sku = $this->skuGenerator->generate($product->name, (int) $product->store_id);
            }

            $product->save();

            $this->auditService->recordCreation($product, $actor);

            return $product;
        });
    }

    /**
     * Update a product sheet, journalling every field that moved.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data, User $actor, ?UploadedFile $photo = null): Product
    {
        return DB::transaction(function () use ($product, $data, $actor, $photo): Product {
            if ($photo) {
                $this->deletePhoto($product);
                $data['photo_path'] = $this->storePhoto($photo);
            }

            // Before the fill: the audit service compares the payload against
            // the values still on the model.
            $this->auditService->recordChanges($product, $data, $actor);

            $product->fill($data)->save();

            return $product->refresh();
        });
    }

    /**
     * Quarantine a defective reference, or release it.
     *
     * A blocked product stays in the catalog and stays countable — it is still
     * physically on our shelf — but it can no longer be picked into an order.
     */
    public function setBlocked(Product $product, bool $blocked, ?string $reason, User $actor): Product
    {
        return DB::transaction(function () use ($product, $blocked, $reason, $actor): Product {
            $product->blocked_at = $blocked ? now() : null;
            $product->blocked_by = $blocked ? $actor->id : null;
            $product->blocked_reason = $blocked ? $reason : null;
            $product->save();

            $this->auditService->recordBlockChange($product->refresh(), $reason, $actor);

            return $product;
        });
    }

    /**
     * Archive a reference.
     *
     * Always a soft delete: order lines, reception lines and ledger entries keep
     * pointing at the product, so the row has to survive its removal from the
     * pickers.
     */
    public function delete(Product $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor): void {
            $this->auditService->recordChanges($product, ['is_active' => false], $actor);

            $product->is_active = false;
            $product->save();
            $product->delete();
        });
    }

    /**
     * Distinct categories already used in the active store, for the autocomplete.
     *
     * @return array<int, string>
     */
    public function categories(): array
    {
        return Product::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    private function storePhoto(UploadedFile $photo): string
    {
        return $photo->store(self::PHOTO_DIRECTORY, 'public');
    }

    private function deletePhoto(Product $product): void
    {
        if ($product->photo_path) {
            Storage::disk('public')->delete($product->photo_path);
        }
    }
}
