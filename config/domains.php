<?php

$hostFromUrl = static function (?string $url): ?string {
    $url = trim((string) $url);

    if ($url === '') {
        return null;
    }

    $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

    return is_string($host) && $host !== '' ? strtolower($host) : null;
};

$schemeFromUrl = static function (?string $url): string {
    $scheme = parse_url((string) $url, PHP_URL_SCHEME);

    return is_string($scheme) && $scheme !== '' ? $scheme : 'https';
};

$appUrl = rtrim((string) env('APP_URL', ''), '/');
$appHost = env('APP_DOMAIN') ?: $hostFromUrl($appUrl);
$marketingHost = env('MARKETING_DOMAIN') ?: null;
$marketingHost = is_string($marketingHost) && trim($marketingHost) !== ''
    ? strtolower(trim($marketingHost))
    : null;

$scheme = $schemeFromUrl($appUrl !== '' ? $appUrl : 'https://localhost');
$marketingUrl = env('MARKETING_URL');
$marketingUrl = is_string($marketingUrl) && trim($marketingUrl) !== ''
    ? rtrim(trim($marketingUrl), '/')
    : ($marketingHost ? $scheme.'://'.$marketingHost : '');

return [

    /*
    |--------------------------------------------------------------------------
    | Marketing site vs back-office hosts
    |--------------------------------------------------------------------------
    |
    | When both hosts are set and different, the public vitrine lives on the
    | marketing host (speedzoneexpress.ma) and the authenticated app lives on
    | the app host (app.speedzoneexpress.ma). Leave MARKETING_DOMAIN empty in
    | local/testing so every route keeps working on APP_URL.
    |
    */

    'app_host' => is_string($appHost) && $appHost !== '' ? strtolower($appHost) : null,
    'marketing_host' => $marketingHost,
    'app_url' => $appUrl,
    'marketing_url' => $marketingUrl,
];
