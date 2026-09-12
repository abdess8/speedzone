<?php

use App\Support\YouCanShopSlug;

it('keeps a bare slug', function () {
    expect(YouCanShopSlug::normalize('Atlas'))->toBe('atlas');
});

it('strips the YouCan hostname a seller would paste from the browser', function (string $input) {
    expect(YouCanShopSlug::normalize($input))->toBe('atlas');
})->with([
    'https://atlas.youcan.shop',
    'http://atlas.youcan.shop/admin',
    'atlas.youcan.shop',
    'https://atlas.youcan.store',
    'https://atlas.ycan.vip',
    'atlas.ycan.shop',
    '  ATLAS.youcan.shop/  ',
]);

it('treats blank input as missing', function () {
    expect(YouCanShopSlug::normalize(null))->toBeNull()
        ->and(YouCanShopSlug::normalize('   '))->toBeNull();
});
