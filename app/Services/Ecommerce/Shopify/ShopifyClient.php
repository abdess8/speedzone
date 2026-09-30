<?php

namespace App\Services\Ecommerce\Shopify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shopify REST Admin API.
 *
 * Dev Dashboard apps exchange a client ID and secret for a 24-hour token.
 * Legacy custom apps still paste a static Admin API token. Either token is
 * sent as `X-Shopify-Access-Token` on every call to
 * `https://{shop}.myshopify.com/admin/api/{version}/…`.
 *
 * @see https://shopify.dev/docs/api/admin-rest
 */
class ShopifyClient
{
    /**
     * Dev Dashboard apps no longer show a static Admin token. Exchange the
     * app's client ID and secret for a 24-hour access token.
     *
     * @return array{access_token: string, expires_in: int, scope: string}
     *
     * @see https://shopify.dev/docs/apps/build/authentication-authorization/client-credentials-grant
     */
    public function exchangeClientCredentials(string $domain, string $clientId, string $clientSecret): array
    {
        $response = Http::timeout((int) config('shopify.timeout', 20))
            ->asForm()
            ->acceptJson()
            ->post('https://'.$domain.'/admin/oauth/access_token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (! $response->successful()) {
            $this->throwIfFailed($response);
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException(__('integrations.shopify.errors.credentials'));
        }

        return [
            'access_token' => $token,
            'expires_in' => max(1, (int) ($response->json('expires_in') ?? 86399)),
            'scope' => (string) ($response->json('scope') ?? ''),
        ];
    }

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

    /**
     * @return array<string, mixed>
     */
    public function order(string $domain, string $token, string $orderId): array
    {
        $response = $this->http($token)->get($this->url($domain, 'orders/'.$orderId.'.json'));

        if ($response->status() === 404) {
            return [];
        }

        $this->throwIfFailed($response);

        $order = $response->json('order');

        return is_array($order) && isset($order['id']) ? $order : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fulfillmentOrders(string $domain, string $token, string $orderId): array
    {
        $response = $this->http($token)->get(
            $this->url($domain, 'orders/'.$orderId.'/fulfillment_orders.json')
        );

        $this->throwIfFailed($response);

        $orders = $response->json('fulfillment_orders');

        return is_array($orders) ? array_values($orders) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fulfillments(string $domain, string $token, string $orderId): array
    {
        $response = $this->http($token)->get(
            $this->url($domain, 'orders/'.$orderId.'/fulfillments.json')
        );

        $this->throwIfFailed($response);

        $fulfillments = $response->json('fulfillments');

        return is_array($fulfillments) ? array_values($fulfillments) : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createFulfillment(string $domain, string $token, array $payload): array
    {
        $response = $this->http($token)->post($this->url($domain, 'fulfillments.json'), [
            'fulfillment' => $payload,
        ]);

        if ($response->status() === 422) {
            return [];
        }

        $this->throwIfFailed($response);

        $fulfillment = $response->json('fulfillment');

        return is_array($fulfillment) ? $fulfillment : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function createFulfillmentEvent(
        string $domain,
        string $token,
        string $orderId,
        string $fulfillmentId,
        string $status,
    ): array {
        $response = $this->http($token)->post(
            $this->url($domain, 'orders/'.$orderId.'/fulfillments/'.$fulfillmentId.'/events.json'),
            ['event' => ['status' => $status]],
        );

        if ($response->status() === 422) {
            return [];
        }

        $this->throwIfFailed($response);

        $event = $response->json('fulfillment_event') ?? $response->json('event');

        return is_array($event) ? $event : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function cancelOrder(string $domain, string $token, string $orderId, array $payload = []): void
    {
        $response = $this->http($token)->post(
            $this->url($domain, 'orders/'.$orderId.'/cancel.json'),
            $payload,
        );

        if (in_array($response->status(), [422, 406], true)) {
            return;
        }

        $this->throwIfFailed($response);
    }

    public function closeOrder(string $domain, string $token, string $orderId): void
    {
        $response = $this->http($token)->post($this->url($domain, 'orders/'.$orderId.'/close.json'));

        if (in_array($response->status(), [422, 406], true)) {
            return;
        }

        $this->throwIfFailed($response);
    }

    public function openOrder(string $domain, string $token, string $orderId): void
    {
        $response = $this->http($token)->post($this->url($domain, 'orders/'.$orderId.'/open.json'));

        if (in_array($response->status(), [422, 406], true)) {
            return;
        }

        $this->throwIfFailed($response);
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

        if ($this->isOauthError($response, $message, 'shop_not_permitted')) {
            throw new RuntimeException(__('integrations.shopify.errors.shop_not_permitted'));
        }

        if ($this->isOauthError($response, $message, 'app_not_installed')
            || str_contains(strtolower($message), 'not installed on this shop')) {
            throw new RuntimeException(__('integrations.shopify.errors.app_not_installed'));
        }

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

    private function isOauthError(Response $response, string $message, string $code): bool
    {
        $haystack = strtolower($message.' '.$response->body());

        return str_contains($haystack, strtolower($code));
    }

    private function errorMessage(Response $response): string
    {
        $description = $response->json('error_description');

        if (is_string($description) && trim($description) !== '') {
            return $description;
        }

        $error = $response->json('error');

        if (is_string($error) && trim($error) !== '') {
            return $error;
        }

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
