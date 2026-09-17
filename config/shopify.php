<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shopify storefront connector
    |--------------------------------------------------------------------------
    |
    | Sellers connect a custom app from Shopify admin and paste the Admin API
    | access token. SpeedZone talks to the REST Admin API
    | (https://shopify.dev/docs/api/admin-rest) with that token.
    |
    */

    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),

    'orders_page_size' => (int) env('SHOPIFY_ORDERS_PAGE_SIZE', 50),

    'orders_max_pages' => (int) env('SHOPIFY_ORDERS_MAX_PAGES', 10),

    // 0 = no date cutoff on the first catalog scan. Later runs use last_synced_at.
    'first_sync_lookback_days' => (int) env('SHOPIFY_FIRST_SYNC_LOOKBACK_DAYS', 0),

    'timeout' => (int) env('SHOPIFY_HTTP_TIMEOUT', 20),

];
