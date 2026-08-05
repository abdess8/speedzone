<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\User;

/**
 * Field-level audit trail of a product sheet.
 *
 * Same contract as {@see OrderAuditService}: values are stored rendered, not
 * raw, so a log line still reads correctly years after the reference data it
 * pointed at has changed.
 *
 * stock_quantity is deliberately absent from the tracked fields — stock lives in
 * its own ledger, with a reason and a document attached to every movement, and
 * duplicating it here would produce two half-truths instead of one record.
 */
class ProductAuditService
{
    /**
     * Product fields whose changes are journalled.
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'name' => 'Name',
        'sku' => 'Reference (SKU)',
        'barcode' => 'Barcode',
        'category' => 'Category',
        'description' => 'Description',
        'unit_price' => 'Selling Price',
        'cost_price' => 'Purchase Cost',
        'is_fragile' => 'Fragile',
        'weight_grams' => 'Weight',
        'length_cm' => 'Length',
        'width_cm' => 'Width',
        'height_cm' => 'Height',
        'photo_path' => 'Photo',
        'is_active' => 'Active',
    ];

    /** Journalled when a hub agent quarantines or releases a reference. */
    public const FIELD_BLOCKED = 'blocked';

    /**
     * @param  array<string, mixed>  $data  Validated update payload.
     * @return array<int, ProductHistory>
     */
    public function recordChanges(Product $product, array $data, ?User $actor): array
    {
        $changes = $this->detectChanges($product, $data);

        if ($changes === []) {
            return [];
        }

        $records = [];

        foreach ($changes as $field => ['old' => $old, 'new' => $new]) {
            $records[] = $product->histories()->create([
                'changed_by' => $actor?->id,
                'field_name' => $field,
                'old_value' => $this->formatValue($field, $old),
                'new_value' => $this->formatValue($field, $new),
            ]);
        }

        return $records;
    }

    /**
     * Journal the creation of a product sheet.
     *
     * A single line rather than one per field: the history is read to answer
     * "what changed", and fifteen rows all saying "was empty" is noise.
     */
    public function recordCreation(Product $product, ?User $actor): ProductHistory
    {
        return $product->histories()->create([
            'changed_by' => $actor?->id,
            'field_name' => 'created',
            'old_value' => null,
            'new_value' => $product->sku,
        ]);
    }

    public function recordBlockChange(Product $product, ?string $reason, ?User $actor): ProductHistory
    {
        return $product->histories()->create([
            'changed_by' => $actor?->id,
            'field_name' => self::FIELD_BLOCKED,
            'old_value' => $product->is_blocked ? null : __('stock.history.blocked'),
            'new_value' => $product->is_blocked ? ($reason ?: __('stock.history.blocked')) : __('stock.history.released'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function detectChanges(Product $product, array $data): array
    {
        $changes = [];

        foreach (array_keys(self::FIELD_LABELS) as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $old = $product->getAttribute($field);
            $new = $data[$field];

            if ($this->valuesAreEqual($field, $old, $new)) {
                continue;
            }

            $changes[$field] = ['old' => $old, 'new' => $new];
        }

        return $changes;
    }

    public static function fieldLabel(string $fieldName): string
    {
        $translationKey = "stock.history.fields.{$fieldName}";
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return self::FIELD_LABELS[$fieldName] ?? ucfirst(str_replace('_', ' ', $fieldName));
    }

    public function formatValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'unit_price', 'cost_price' => number_format((float) $value, 2, '.', '').' MAD',
            'is_fragile', 'is_active' => $value ? __('common.yes') : __('common.no'),
            'weight_grams' => number_format((float) $value, 0, '.', ' ').' g',
            'length_cm', 'width_cm', 'height_cm' => number_format((float) $value, 2, '.', '').' cm',
            'photo_path' => __('stock.history.photo_updated'),
            default => (string) $value,
        };
    }

    private function valuesAreEqual(string $field, mixed $old, mixed $new): bool
    {
        if (in_array($field, ['is_fragile', 'is_active'], true)) {
            return (bool) $old === (bool) $new;
        }

        if (in_array($field, ['unit_price', 'cost_price', 'length_cm', 'width_cm', 'height_cm'], true)) {
            // A nulled cost and a zero cost are different statements: one means
            // "not tracked", the other "free".
            if ($old === null || $new === null) {
                return $old === $new;
            }

            return round((float) $old, 2) === round((float) $new, 2);
        }

        if ($field === 'weight_grams') {
            if ($old === null || $new === null) {
                return $old === $new;
            }

            return (int) $old === (int) $new;
        }

        return (string) ($old ?? '') === (string) ($new ?? '');
    }
}
