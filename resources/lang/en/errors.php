<?php

return [
    'login' => 'Sign in',
    'home' => 'Back to home',
    'back' => 'Previous page',
    403 => [
        'title' => 'Access denied',
        'description' => 'Your account is not allowed to open this page. Use the menu to reach a section you can access.',
    ],
    404 => [
        'title' => 'Page not found',
        'description' => 'This address does not exist, or the resource has moved. Go home or pick another section from the menu.',
    ],
    419 => [
        'title' => 'Session expired',
        'description' => 'Your session is no longer valid. Refresh the page and try again.',
    ],
    429 => [
        'title' => 'Too many requests',
        'description' => 'Please wait a moment before trying again.',
    ],
    500 => [
        'title' => 'Server error',
        'description' => 'Something went wrong on our side. Try again in a moment, or return home.',
    ],
    503 => [
        'title' => 'Service unavailable',
        'description' => 'The application is temporarily unavailable. Please try again in a few minutes.',
    ],
];
