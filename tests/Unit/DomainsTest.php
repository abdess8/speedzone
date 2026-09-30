<?php

use App\Support\Domains;

test('split stays off when the marketing host is empty', function () {
    config([
        'domains.app_host' => 'app.speedzoneexpress.ma',
        'domains.marketing_host' => null,
    ]);

    expect(Domains::splitEnabled())->toBeFalse();
});

test('split turns on when both hosts differ', function () {
    config([
        'domains.app_host' => 'app.speedzoneexpress.ma',
        'domains.marketing_host' => 'speedzoneexpress.ma',
        'domains.app_url' => 'https://app.speedzoneexpress.ma',
        'domains.marketing_url' => 'https://speedzoneexpress.ma',
    ]);

    expect(Domains::splitEnabled())->toBeTrue()
        ->and(Domains::isMarketingHost('speedzoneexpress.ma'))->toBeTrue()
        ->and(Domains::isMarketingHost('www.speedzoneexpress.ma'))->toBeTrue()
        ->and(Domains::isAppHost('app.speedzoneexpress.ma'))->toBeTrue()
        ->and(Domains::isMarketingPath('/'))->toBeTrue()
        ->and(Domains::isMarketingPath('/tracking/SPD-1'))->toBeTrue()
        ->and(Domains::isMarketingPath('/login'))->toBeFalse()
        ->and(Domains::isMarketingPath('/dashboard'))->toBeFalse();
});
