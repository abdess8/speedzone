<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Builds the reference of a product the vendor did not name himself.
 *
 * The reference is derived from the product name rather than being a bare
 * counter, because it ends up read aloud in a warehouse: "TSHI-4821" survives a
 * phone call, "000317" does not.
 */
class SkuGenerator
{
    /** Letters taken from the product name to open the reference. */
    private const STEM_LENGTH = 4;

    /** Digits appended to keep two similarly named products apart. */
    private const RANDOM_DIGITS = 4;

    /**
     * Generate a reference unique inside the given store.
     */
    public function generate(string $productName, int $storeId): string
    {
        $stem = $this->stem($productName);

        // Bounded rather than infinite: a store that somehow exhausted the
        // 9000 suffixes of one stem falls back to a longer random tail instead
        // of spinning against the database.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = sprintf('%s-%d', $stem, random_int(1000, 9999));

            if (! $this->exists($candidate, $storeId)) {
                return $candidate;
            }
        }

        do {
            $candidate = sprintf('%s-%s', $stem, strtoupper(Str::random(6)));
        } while ($this->exists($candidate, $storeId));

        return $candidate;
    }

    /**
     * Uppercase ASCII stem of a product name.
     *
     * Accents are folded rather than dropped so "Écharpe" yields ECHA and not a
     * two-letter stub.
     */
    private function stem(string $productName): string
    {
        $letters = preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii($productName))) ?? '';

        if ($letters === '') {
            return 'PRD';
        }

        return substr($letters, 0, self::STEM_LENGTH);
    }

    /**
     * References are unique per store, so the check has to ignore the active
     * store boundary and target the owning one explicitly.
     */
    private function exists(string $sku, int $storeId): bool
    {
        return Product::acrossStores()
            ->withTrashed()
            ->where('store_id', $storeId)
            ->where('sku', $sku)
            ->exists();
    }
}
