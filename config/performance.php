<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slow request logging
    |--------------------------------------------------------------------------
    |
    | When enabled, any request that takes longer than the threshold is written
    | to the log with its duration, query count and total query time. This is
    | the quickest way to find out whether a slow production page is spending
    | its time in PHP or in MySQL.
    |
    | Keep it enabled in production: the listener only runs when a request is
    | actually slow, and the overhead of counting queries is negligible.
    |
    */

    'log_slow_requests' => (bool) env('LOG_SLOW_REQUESTS', true),

    'slow_request_threshold_ms' => (int) env('SLOW_REQUEST_THRESHOLD_MS', 500),

    /*
    |--------------------------------------------------------------------------
    | Slow query logging
    |--------------------------------------------------------------------------
    |
    | Individual queries slower than this are logged on their own, together with
    | the route that issued them. Set to 0 to disable.
    |
    */

    'slow_query_threshold_ms' => (int) env('SLOW_QUERY_THRESHOLD_MS', 200),

    /*
    |--------------------------------------------------------------------------
    | Dashboard cache
    |--------------------------------------------------------------------------
    |
    | How long a computed dashboard payload is reused, in seconds. The dashboard
    | aggregates the whole orders table, so serving it from cache is what keeps
    | the endpoint fast under concurrent users. Lower it if the figures need to
    | be closer to real time.
    |
    */

    'dashboard_cache_ttl' => (int) env('DASHBOARD_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Reference data cache
    |--------------------------------------------------------------------------
    |
    | Lifetime, in seconds, of rarely changing lookup lists such as the active
    | cities used to build dropdowns. These caches are also flushed on write, so
    | the TTL is only a safety net.
    |
    */

    'reference_cache_ttl' => (int) env('REFERENCE_CACHE_TTL', 3600),

];
