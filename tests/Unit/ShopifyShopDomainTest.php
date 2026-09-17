<?php

use App\Support\ShopifyShopDomain;

it('normalizes a Shopify hostname to the canonical myshopify.com form', function (string $input) {
    expect(ShopifyShopDomain::normalize($input))->toBe('atlas.myshopify.com');
})->with([
    'atlas',
    'Atlas',
    'atlas.myshopify.com',
    'https://atlas.myshopify.com',
    'https://atlas.myshopify.com/admin',
    'http://atlas.myshopify.com/admin/apps',
    '  ATLAS.myshopify.com/  ',
]);

it('rejects values that are not a Shopify shop slug', function (?string $input) {
    expect(ShopifyShopDomain::normalize($input))->toBeNull();
})->with([
    null,
    '   ',
    'atlas.youcan.shop',
    'not a shop',
    '-atlas',
    'atlas-.myshopify.com',
]);
