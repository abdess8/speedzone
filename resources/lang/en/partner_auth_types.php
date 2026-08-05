<?php

return [
    'BASIC' => 'Username & Password (Basic Auth)',
    'BEARER' => 'Bearer Token (static)',
    'API_KEY' => 'API Key (header)',
    'LOGIN_TOKEN' => 'Login endpoint → Token',

    'descriptions' => [
        'BASIC' => 'HTTP Basic authentication using username (Client ID) and password (Client Secret).',
        'BEARER' => 'A static bearer token sent in the Authorization header.',
        'API_KEY' => 'An API key sent in a custom HTTP header (default: X-API-Key).',
        'LOGIN_TOKEN' => 'POST credentials to the login endpoint, obtain a token (e.g. data.token), then use Bearer auth for all API calls (Sendit: public_key + secret_key).',
    ],
];
