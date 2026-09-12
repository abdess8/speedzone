<?php

return [

    /*
    |--------------------------------------------------------------------------
    | YouCan seller connector
    |--------------------------------------------------------------------------
    |
    | Sellers sign in through YouCan Accounts SSO (the same email / password
    | as seller-area.youcan.shop). The public Store Admin host no longer
    | exposes POST /auth/login.
    |
    */

    'seller_area_url' => env('YOUCAN_SELLER_AREA_URL', 'https://seller-area.youcan.shop'),

    'accounts_url' => env('YOUCAN_ACCOUNTS_URL', 'https://accounts.youcan.shop'),

    'api_base_url' => env('YOUCAN_API_BASE_URL', 'https://api.youcan.shop'),

    'orders_page_size' => (int) env('YOUCAN_ORDERS_PAGE_SIZE', 50),

    'orders_max_pages' => (int) env('YOUCAN_ORDERS_MAX_PAGES', 10),

    // 0 = no date cutoff on the first catalog scan. Later runs use last_synced_at.
    'first_sync_lookback_days' => (int) env('YOUCAN_FIRST_SYNC_LOOKBACK_DAYS', 0),

];
