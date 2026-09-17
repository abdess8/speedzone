<?php

namespace App\Services\Ecommerce\Shopify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shopify REST Admin API.
 *
 * Custom apps issue a permanent Admin API token in the Shopify admin. That
 * token is sent as `X-Shopify-Access-Token` on every call to
 * `https://{shop}.myshopify.com/admin/api/{version}/…`.
 *
 * @see https://shopify.dev/docs/api/admin-rest
 */
class ShopifyClient
{
    /**
     * @return array{id: string, name: string, domain: string, email: ?string}
     */
    public function shop(string $domain, string $token): array
    {
        $response = $this->http($token)->get($this->url($domain, 'shop.json'));

        $this->throwIfFailed($response);

        $shop = $response->json('shop');

        if (! is_array($shop) || blank($shop['id'] ?? null)) {
            throw new RuntimeException(__('integrations.shopify.errors.shop'));
        }

        return [
            'id' => (string) $shop['id'],
            'name' => (string) ($shop['name'] ?? $domain),
            'domain' => (string) ($shop['myshopify_domain'] ?? $shop['domain'] ?? $domain),
            'email' => isset($shop['email']) ? (string) $shop['email'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{orders: array<int, array<string, mixed>>, next_page_info: ?string}
     */
    public function orders(string $domain, string $token, array $query = []): array
    {
        $response = $this->http($token)->get($this->url($domain, 'orders.json'), $query);

        $this->throwIfFailed($response);

        $orders = $response->json('orders');

        return [
            'orders' => is_array($orders) ? array_values($orders) : [],
            'next_page_info' => $this->nextPageInfo($response),
        ];
    }

    private function http(string $token): PendingRequest
    {
        return Http::timeout((int) config('shopify.timeout', 20))
            ->acceptJson()
            ->withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ]);
    }

    private function url(string $domain, string $path): string
    {
        $version = trim((string) config('shopify.api_version', '2026-07'), '/');

        return 'https://'.$domain.'/admin/api/'.$version.'/'.ltrim($path, '/');
    }

    private function throwIfFailed(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $message = $this->errorMessage($response);

        if ($status === 401 || $status === 403) {
            throw new RuntimeException(__('integrations.shopify.errors.credentials'));
        }

        if ($status === 404) {
            throw new RuntimeException(__('integrations.shopify.errors.shop'));
        }

        if ($status === 429) {
            throw new RuntimeException(__('integrations.shopify.errors.rate_limit'));
        }

        throw new RuntimeException($message ?: __('integrations.shopify.errors.http', ['status' => $status]));
    }

    private function errorMessage(Response $response): string
    {
        $errors = $response->json('errors');

        if (is_string($errors) && trim($errors) !== '') {
            return $errors;
        }

        if (is_array($errors) && $errors !== []) {
            $first = reset($errors);

            if (is_string($first) && trim($first) !== '') {
                return $first;
            }

            if (is_array($first) && isset($first[0]) && is_string($first[0])) {
                return $first[0];
            }
        }

        return '';
    }

    private function nextPageInfo(Response $response): ?string
    {
        $link = $response->header('Link') ?: $response->header('link');

        if (! is_string($link) || $link === '') {
            return null;
        }

        foreach (explode(',', $link) as $part) {
            if (! str_contains($part, 'rel="next"')) {
                continue;
            }

            if (preg_match('/<([^>]+)>/', $part, $matches) !== 1) {
                continue;
            }

            $query = parse_url($matches[1], PHP_URL_QUERY);

            if (! is_string($query) || $query === '') {
                return null;
            }

            parse_str($query, $params);

            $info = $params['page_info'] ?? null;

            return is_string($info) && $info !== '' ? $info : null;
        }

        return null;
    }
}
