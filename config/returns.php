<?php

return [
    'reference_prefix' => env('RETURN_REFERENCE_PREFIX', 'RTN'),
    'reference_random_digits' => (int) env('RETURN_REFERENCE_RANDOM_DIGITS', 6),
    'tracking_base_url' => env('RETURN_TRACKING_BASE_URL', env('APP_URL')),
];
