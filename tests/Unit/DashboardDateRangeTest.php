<?php

use App\Support\DashboardDateRange;
use Illuminate\Http\Request;

test('dashboard date range defaults to all time', function () {
    $range = DashboardDateRange::fromRequest(Request::create('/api/dashboard', 'GET'));

    expect($range->period)->toBe('all_time')
        ->and($range->start->toDateString())->toBe('1970-01-01')
        ->and($range->end->toDateString())->toBe(now()->toDateString());
});
