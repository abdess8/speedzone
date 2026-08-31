<?php

namespace App\Http\Requests;

use App\Support\StoreContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk catalog import.
 *
 * Mirrors {@see ImportOrdersRequest}: the wizard already sends canonical rows,
 * but this endpoint is reachable by anything holding the seller's session, so
 * the payload is treated as untrusted input rather than as output of our screen.
 */
class ImportProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function maxRows(): int
    {
        return (int) config('stock.import_max_rows', 1000);
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('products');

        if (! is_array($rows)) {
            return;
        }

        $this->merge([
            'products' => array_map(
                fn ($row) => is_array($row) ? $this->normalizeRow($row) : $row,
                $rows
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        foreach (['sku', 'barcode', 'category', 'description'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            $row[$key] = $value === '' ? null : $value;
        }

        $row['is_fragile'] = filter_var($row['is_fragile'] ?? false, FILTER_VALIDATE_BOOL);

        foreach (['cost_price', 'weight_grams'] as $key) {
            if (($row[$key] ?? '') === '') {
                $row[$key] = null;
            }
        }

        return $row;
    }

    /**
     * Per-row rules.
     *
     * Declared through the `products.*` wildcard rather than row by row, because
     * `distinct` only compares siblings when the attribute reaches the validator
     * as a wildcard: spelled out per index it would quietly pass, and a file
     * declaring the same reference twice would collide on insert halfway through
     * the batch.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $storeId = app(StoreContext::class)->id();
        $unique = function (string $column) use ($storeId) {
            $rule = Rule::unique('products', $column);

            return $storeId === null ? $rule : $rule->where('store_id', $storeId);
        };

        return [
            'products' => ['required', 'array', 'min:1', 'max:'.$this->maxRows()],
            'products.*' => ['required', 'array'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.sku' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._\-\/]+$/',
                'distinct:ignore_case',
                $unique('sku'),
            ],
            'products.*.barcode' => [
                'nullable',
                'string',
                'max:64',
                'distinct:ignore_case',
                $unique('barcode'),
            ],
            'products.*.category' => ['nullable', 'string', 'max:255'],
            'products.*.description' => ['nullable', 'string', 'max:5000'],
            'products.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'products.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'products.*.is_fragile' => ['boolean'],
            'products.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            // The opening stock a vendor already holds. Credited through the
            // ledger like any other movement, never written straight to the
            // product row.
            'products.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'products.*.sku.unique' => __('stock.products.validation.sku_taken'),
            'products.*.sku.distinct' => __('stock.products.validation.sku_duplicated_in_file'),
            'products.*.sku.regex' => __('stock.products.validation.sku_format'),
            'products.*.barcode.unique' => __('stock.products.validation.barcode_taken'),
            'products.*.barcode.distinct' => __('stock.products.validation.barcode_duplicated_in_file'),
            'products.max' => __('stock.products.validation.too_many_rows', ['max' => $this->maxRows()]),
        ];
    }

    /**
     * Row payloads, keyed by their position in the submitted batch.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return array_values($this->validated()['products']);
    }
}
