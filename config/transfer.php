<?php

return [
    'reference_prefix' => env('TRANSFER_REFERENCE_PREFIX', 'TRF'),
    'reference_random_digits' => (int) env('TRANSFER_REFERENCE_RANDOM_DIGITS', 6),

    /** Default origin hub city ID (e.g. Tangier depot). */
    'default_from_city_id' => env('TRANSFER_DEFAULT_FROM_CITY_ID'),

    'tracking_base_url' => env('TRANSFER_TRACKING_BASE_URL', env('APP_URL')),
];
