<?php

namespace App\Services\Ecommerce\YouCan;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * YouCan seller authentication.
 *
 * The public Store Admin host (`api.youcan.shop`) no longer exposes
 * `POST /auth/login` — that route 404s. Sellers now sign in through YouCan
 * Accounts SSO (`accounts.youcan.shop/sso/login`), the same flow as
 * seller-area.youcan.shop. SpeedZone then lists shops (`GET /shop/stores`)
 * and scopes the session with `POST /admin/{id}/switch-store`.
 */
class YouCanClient
{
    private ?CookieJar $jar = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $selectedStore = null;

    /**
     * @return array{
     *     access_token: string,
     *     expired_at: ?Carbon,
     *     stores: array<int, array{store_id: string, slug: string, is_active: bool, name?: string}>
     * }
     */
    public function login(string $email, string $password, ?string $slug = null, ?string $twoFactorCode = null): array
    {
        $this->jar = new CookieJar;
        $this->selectedStore = null;

        $sessionId = $this->startSsoSession();
        $this->submitSsoLogin($email, $password, $sessionId, $twoFactorCode);

        $stores = $this->listStores();

        if ($stores === []) {
            throw new RuntimeException(__('integrations.youcan.errors.credentials'));
        }

        $this->establishSellerAreaSession();

        return [
            'access_token' => 'sso:'.($stores[0]['store_id'] ?? Str::uuid()->toString()),
            'expired_at' => Carbon::now()->addDays(6),
            'stores' => $stores,
        ];
    }

    /**
     * Bind the seller-area session onto one of the shops the seller owns.
     *
     * @return array{access_token: string, expired_at: ?Carbon, stores: array<int, array<string, mixed>>}
     */
    public function switchStore(string $accessToken, string $youCanStoreId): array
    {
        $response = $this->http($this->sellerAreaXsrf())
            ->accept('text/html, application/json')
            ->asForm()
            ->withHeaders([
                'Origin' => rtrim((string) config('youcan.seller_area_url'), '/'),
                'Referer' => $this->sellerAreaUrl('/admin/'),
            ])
            ->post($this->sellerAreaUrl('/admin/'.$youCanStoreId.'/switch-store'));

        if ($response->failed() && ! $response->redirect()) {
            throw new RuntimeException($this->unauthorizedOr($response));
        }

        $this->selectedStore = [
            'store_id' => $youCanStoreId,
        ];

        // The browser lands on /admin after the switch; that is what binds the
        // seller-area session to the chosen shop before JSON calls.
        $this->http()
            ->accept('text/html')
            ->get($this->sellerAreaUrl('/admin/'));

        return [
            'access_token' => 'sso:'.$youCanStoreId,
            'expired_at' => Carbon::now()->addDays(6),
            'stores' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function me(string $accessToken): array
    {
        $response = $this->sellerAreaJson()
            ->get($this->sellerAreaUrl('/admin/web/api/dev/get-dotshop-object'));

        if (! $response->successful()) {
            throw new RuntimeException($this->unauthorizedOr($response));
        }

        $payload = $response->json() ?? [];
        $store = is_array($payload['store'] ?? null) ? $payload['store'] : [];
        $owner = is_array($payload['owner'] ?? null) ? $payload['owner'] : [];

        return [
            'slug' => $store['slug'] ?? null,
            'name' => $store['name'] ?? null,
            'store_id' => $store['id'] ?? $this->selectedStore['store_id'] ?? null,
            'email' => $owner['email'] ?? null,
        ];
    }

    /**
     * Re-run the seller SSO and land on the shop this SpeedZone connection
     * already picked. Cron and the "sync now" button both need a fresh cookie
     * jar: we do not persist YouCan session cookies.
     */
    public function authenticateForStore(
        string $email,
        string $password,
        string $youCanStoreId,
        ?string $twoFactorCode = null,
    ): void {
        $this->login($email, $password, null, $twoFactorCode);
        $this->switchStore('sso:'.$youCanStoreId, $youCanStoreId);

        $me = $this->me('sso:'.$youCanStoreId);

        if (blank($me['slug'] ?? null)) {
            throw new RuntimeException(__('integrations.youcan.errors.unauthorized'));
        }
    }

    /**
     * Newest-first page of YouCan orders for the shop currently selected.
     *
     * @return array{orders: array<int, array<string, mixed>>, has_more: bool, page: int}
     */
    public function orders(int $page = 1, int $perPage = 50): array
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        $response = $this->sellerAreaJson()
            ->timeout(45)
            ->get($this->sellerAreaUrl('/admin/web/api/orders'), [
                'page' => $page,
                'limit' => $perPage,
                'per_page' => $perPage,
                'include' => 'customer',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->unauthorizedOr($response));
        }

        $payload = $response->json() ?? [];
        $orders = $payload['data'] ?? $payload['orders'] ?? [];

        if (is_array($orders) && ! array_is_list($orders)) {
            $orders = $orders['data'] ?? $orders['orders'] ?? [];
        }

        if (! is_array($orders)) {
            $orders = [];
        }

        $list = [];

        foreach ($orders as $order) {
            if (is_array($order) && isset($order['id'])) {
                $list[] = $order;
            }
        }

        $lastPage = (int) (
            data_get($payload, 'meta.pagination.last_page')
            ?? data_get($payload, 'meta.last_page')
            ?? data_get($payload, 'meta.lastPage')
            ?? data_get($payload, 'pagination.total_pages')
            ?? data_get($payload, 'pagination.last_page')
            ?? 0
        );
        $nextLink = data_get($payload, 'meta.pagination.links.next')
            ?? data_get($payload, 'meta.links.next')
            ?? data_get($payload, 'links.next');

        $hasMore = (is_string($nextLink) && $nextLink !== '')
            || ($lastPage > 0 && $page < $lastPage)
            || ($lastPage === 0 && ! is_string($nextLink) && count($list) >= $perPage);

        return [
            'orders' => $list,
            'has_more' => $hasMore,
            'page' => $page,
        ];
    }

    /**
     * Full order row. The index payload sometimes omits COD extra fields.
     *
     * @return array<string, mixed>
     */
    public function order(string $id): array
    {
        $id = trim($id);

        if ($id === '') {
            return [];
        }

        $response = $this->sellerAreaJson()
            ->timeout(45)
            ->get($this->sellerAreaUrl('/admin/orders/'.$id));

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json() ?? [];
        $order = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        return is_array($order) && isset($order['id']) ? $order : [];
    }

    private function startSsoSession(): string
    {
        $response = $this->http()
            ->accept('text/html')
            ->get($this->sellerAreaUrl('/youcan-idp/authenticate'));

        $sessionId = $this->sessionIdFrom($response);

        if ($sessionId === '') {
            throw new RuntimeException(__('integrations.youcan.errors.sso'));
        }

        $this->http()->get($this->accountsUrl('/sanctum/csrf-cookie'));

        return $sessionId;
    }

    private function submitSsoLogin(string $email, string $password, string $sessionId, ?string $twoFactorCode): void
    {
        $response = $this->http($this->accountsXsrf())
            ->withOptions(['allow_redirects' => false])
            ->accept('text/html, application/json')
            ->asForm()
            ->withHeaders([
                'Origin' => rtrim((string) config('youcan.accounts_url'), '/'),
                'Referer' => $this->accountsUrl('/sso/login'),
            ])
            ->post($this->accountsUrl('/sso/login?session_id='.urlencode($sessionId)), [
                'username' => $email,
                'password' => $password,
                'session_id' => $sessionId,
            ]);

        if ($response->clientError() || $response->serverError()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->status()) ?: __('integrations.youcan.errors.credentials'));
        }

        $finalUrl = $this->effectiveUrl($response);

        if (str_contains($finalUrl, '/2fa') || str_contains($finalUrl, 'twostep')) {
            $this->submitTwoFactor($twoFactorCode);
        }
    }

    private function submitTwoFactor(?string $twoFactorCode): void
    {
        if (blank($twoFactorCode)) {
            throw new RuntimeException(__('integrations.youcan.errors.two_factor'));
        }

        $response = $this->http($this->accountsXsrf())
            ->withOptions(['allow_redirects' => false])
            ->accept('text/html, application/json')
            ->asForm()
            ->post($this->accountsUrl('/account/2fa/verification'), [
                'code' => $twoFactorCode,
            ]);

        if ($response->failed() && ! $response->redirect()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->status()) ?: __('integrations.youcan.errors.two_factor'));
        }
    }

    /**
     * @return array<int, array{store_id: string, slug: string, is_active: bool, name: string}>
     */
    private function listStores(): array
    {
        $response = $this->http($this->accountsXsrf())
            ->acceptJson()
            ->get($this->accountsUrl('/shop/stores'));

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->status()) ?: __('integrations.youcan.errors.credentials'));
        }

        $data = $response->json('data') ?? $response->json() ?? [];

        if (! is_array($data)) {
            return [];
        }

        $list = [];

        foreach ($data as $store) {
            if (! is_array($store) || blank($store['id'] ?? null)) {
                continue;
            }

            $list[] = [
                'store_id' => (string) $store['id'],
                'slug' => (string) ($store['slug'] ?? ''),
                'name' => (string) ($store['name'] ?? ''),
                'is_active' => (bool) ($store['active'] ?? $store['is_active'] ?? false),
            ];
        }

        return $list;
    }

    private function establishSellerAreaSession(): void
    {
        $this->http()
            ->accept('text/html')
            ->get($this->sellerAreaUrl('/youcan-idp/authenticate'));
    }

    private function sessionIdFrom(Response $response): string
    {
        $candidates = [
            $this->effectiveUrl($response),
            (string) $response->header('Location'),
        ];

        foreach ($candidates as $url) {
            if ($url === '') {
                continue;
            }

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $sessionId = $query['session_id'] ?? null;

            if (is_string($sessionId) && $sessionId !== '') {
                return $sessionId;
            }
        }

        return '';
    }

    private function effectiveUrl(Response $response): string
    {
        if ($response->redirect() && filled($response->header('Location'))) {
            return (string) $response->header('Location');
        }

        $uri = $response->effectiveUri();

        return $uri ? (string) $uri : '';
    }

    /**
     * @return array<string, string>
     */
    private function accountsXsrf(): array
    {
        return $this->xsrfHeader('XSRF-TOKEN-ACCOUNTS');
    }

    /**
     * @return array<string, string>
     */
    private function sellerAreaXsrf(): array
    {
        $header = $this->xsrfHeader('XSRF-TOKEN');

        return $header === [] ? $this->xsrfHeader('XSRF-TOKEN-ACCOUNTS') : $header;
    }

    /**
     * @return array<string, string>
     */
    private function xsrfHeader(string $cookieName): array
    {
        foreach ($this->jar()->toArray() as $cookie) {
            if (($cookie['Name'] ?? null) !== $cookieName) {
                continue;
            }

            $value = urldecode((string) ($cookie['Value'] ?? ''));

            if ($value !== '') {
                return ['X-XSRF-TOKEN' => $value];
            }
        }

        return [];
    }

    private function sellerAreaJson(): PendingRequest
    {
        return $this->http($this->sellerAreaXsrf())
            ->acceptJson()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin' => rtrim((string) config('youcan.seller_area_url'), '/'),
                'Referer' => $this->sellerAreaUrl('/admin/'),
            ]);
    }

    private function http(array $headers = []): PendingRequest
    {
        $request = Http::timeout(30)
            ->connectTimeout(10)
            ->withUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36')
            ->withOptions([
                'cookies' => $this->jar(),
                'allow_redirects' => ['max' => 10, 'track_redirects' => true],
            ]);

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        return $request;
    }

    private function jar(): CookieJar
    {
        return $this->jar ??= new CookieJar;
    }

    private function sellerAreaUrl(string $path): string
    {
        return rtrim((string) config('youcan.seller_area_url'), '/').$path;
    }

    private function accountsUrl(string $path): string
    {
        return rtrim((string) config('youcan.accounts_url'), '/').$path;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function errorMessage(?array $payload, int $status): string
    {
        if (! is_array($payload)) {
            return __('integrations.youcan.errors.http', ['status' => $status]);
        }

        if (is_array($payload['errors'] ?? null)) {
            $first = collect($payload['errors'])->flatten()->first();

            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        $detail = $payload['detail'] ?? $payload['message'] ?? $payload['error'] ?? null;

        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        return __('integrations.youcan.errors.http', ['status' => $status]);
    }

    private function unauthorizedOr(Response $response): string
    {
        if ($response->status() === 401 || $response->status() === 403) {
            return __('integrations.youcan.errors.unauthorized');
        }

        return $this->errorMessage($response->json(), $response->status());
    }
}
