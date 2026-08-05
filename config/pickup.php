<?php

return [
    'reference_prefix' => env('PICKUP_REFERENCE_PREFIX', 'PU'),
    'reference_sequence_digits' => (int) env('PICKUP_REFERENCE_SEQUENCE_DIGITS', 6),

    'delivery_note' => [
        'company_name' => env('PICKUP_COMPANY_NAME', env('ORDER_LABEL_COMPANY_NAME', 'SpeedZone Express')),
        'logo_path' => env('PICKUP_LOGO_PATH', env('ORDER_LABEL_LOGO_PATH')),
        'paper' => 'a4',
    ],
];
