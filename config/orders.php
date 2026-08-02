<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company / Tracking Number
    |--------------------------------------------------------------------------
    |
    | The company code prefixes every generated tracking number, producing
    | identifiers like SPD-2026-583920. The tracking number doubles as the
    | human-facing order number.
    |
    */

    'company_code' => env('ORDER_COMPANY_CODE', 'SPD'),

    /*
    | Number of random digits appended to the tracking number.
    */
    'tracking_random_digits' => 6,

    /*
    |--------------------------------------------------------------------------
    | Public Tracking URL
    |--------------------------------------------------------------------------
    |
    | Base URL used when building the QR code target. The final URL looks like
    | https://app.domain.com/orders/SPD-2026-583920
    |
    */

    'tracking_base_url' => env('ORDER_TRACKING_BASE_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | Shipping Label
    |--------------------------------------------------------------------------
    */

    'label' => [
        // Absolute path or public-relative path to the company logo printed on labels.
        'logo_path' => env('ORDER_LABEL_LOGO', public_path('images/logo-dark.png')),
        'company_name' => env('ORDER_LABEL_COMPANY', 'SpeedZone Express'),
        // Thermal label dimensions in points (100mm x 150mm ~ 283x425pt).
        'paper_width' => 283,
        'paper_height' => 425,
    ],

];
