<?php

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

/**
 * Raised when a movement would push a product below zero.
 *
 * Reaching this from the UI means two people picked the same last unit at the
 * same time: the pick-list already hides out-of-stock references, so the check
 * that throws this lives under the row lock, where it is actually decisive.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(__('stock.errors.insufficient', [
            'product' => $product->name,
            'available' => $available,
            'requested' => $requested,
        ]));
    }

    /**
     * Validation-style payload, so a controller can hand it back on the field
     * the seller can actually fix.
     *
     * @return array<string, string>
     */
    public function toValidationErrors(string $key): array
    {
        return [$key => $this->getMessage()];
    }
}
