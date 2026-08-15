<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public company profile
    |--------------------------------------------------------------------------
    |
    | Contact details published on the marketing site (footer, structured data)
    | and available to any other feature that has to print or send them.
    |
    */

    'name' => env('COMPANY_NAME', 'SpeedZone Express'),

    'address' => env('COMPANY_ADDRESS', '48, Avenue Bakr Essadik, Kénitra'),

    'city' => env('COMPANY_CITY', 'Kénitra'),

    'country_code' => env('COMPANY_COUNTRY_CODE', 'MA'),

    /*
    | Displayed as-is; `phone_link` is the E.164 form used in tel: links.
    */

    'phone' => env('COMPANY_PHONE', '0663795006'),

    'phone_link' => env('COMPANY_PHONE_LINK', '+212663795006'),

    'email' => env('COMPANY_EMAIL', 'speedzoneepxress@gmail.com'),

    'social' => [
        'instagram' => env('COMPANY_INSTAGRAM', 'https://www.instagram.com/speedzoneexpress'),
    ],

];
