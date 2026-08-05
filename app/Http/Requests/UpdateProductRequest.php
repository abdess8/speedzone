<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Validation\Rules\Unique;

class UpdateProductRequest extends StoreProductRequest
{
    /**
     * Same rules as creation, with the product excluding itself from the
     * uniqueness checks — saving a sheet without touching its reference must not
     * report that reference as taken.
     */
    protected function uniqueInStore(string $column): Unique
    {
        $rule = parent::uniqueInStore($column);
        $product = $this->route('product');

        return $product instanceof Product ? $rule->ignore($product->getKey()) : $rule;
    }
}
