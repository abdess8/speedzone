<?php

namespace App\Http\Requests;

use App\Support\StoreContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_fragile' => $this->boolean('is_fragile'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            // A reference left blank is generated from the product name; an empty
            // string would otherwise be stored and break the uniqueness promise.
            'sku' => $this->filled('sku') ? trim((string) $this->input('sku')) : null,
            'barcode' => $this->filled('barcode') ? trim((string) $this->input('barcode')) : null,
            'category' => $this->filled('category') ? trim((string) $this->input('category')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-\/]+$/', $this->uniqueInStore('sku')],
            'barcode' => ['nullable', 'string', 'max:64', $this->uniqueInStore('barcode')],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_fragile' => ['boolean'],
            'is_active' => ['boolean'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'length_cm' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'width_cm' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => __('stock.products.validation.sku_format'),
            'sku.unique' => __('stock.products.validation.sku_taken'),
            'barcode.unique' => __('stock.products.validation.barcode_taken'),
        ];
    }

    /**
     * Uniqueness inside the active shop.
     *
     * Two vendors both selling "TSHIRT-M" is normal, so the constraint is scoped
     * rather than global. Soft-deleted rows are included: the database index does
     * not know about deleted_at, and reusing an archived reference would collide.
     */
    protected function uniqueInStore(string $column): Unique
    {
        $rule = Rule::unique('products', $column);
        $storeId = app(StoreContext::class)->id();

        if ($storeId !== null) {
            $rule->where('store_id', $storeId);
        }

        return $rule;
    }

    /**
     * Validated product attributes, without the file upload.
     *
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        return collect($this->validated())->except('photo')->all();
    }
}
