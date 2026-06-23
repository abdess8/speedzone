<?php

namespace App\Services\Partners;

use App\Enums\PartnerAuthType;
use App\Models\ApiLog;
use App\Models\Partner;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolves and applies partner authentication (Basic, Bearer, API Key, Login+Token).
 */
class PartnerAuthenticator
{
    private const DEFAULT_TOKEN_TTL_SECONDS = 3600;

    /** @var array<int, string> In-memory tokens for unsaved partner probes during tests. */
    private array $runtimeTokens = [];

    public function __construct(
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function apply(PendingRequest $client, Partner $partner): PendingRequest
    {
        return match (PartnerAuthType::resolve($partner->auth_type)) {
            PartnerAuthType::BASIC => $this->applyBasic($client, $partner),
            PartnerAuthType::BEARER => $this->applyBearer($client, $partner),
            PartnerAuthType::API_KEY => $this->applyApiKey($client, $partner),
            PartnerAuthType::LOGIN_TOKEN => $this->applyLoginToken($client, $partner),
        };
    }

    /**
     * Obtain a fresh token via the partner login endpoint.
     *
     * @return array<string, mixed>
     *
     * @throws PartnerApiException
     */
    public function login(Partner $partner, bool $force = false): array
    {
        if (PartnerAuthType::resolve($partner->auth_type) !== PartnerAuthType::LOGIN_TOKEN) {
            throw new PartnerApiException('Login is only available for LOGIN_TOKEN authentication.');
        }

        if (blank($partner->endpoint_login)) {
            throw new PartnerApiException('Login endpoint is not configured.');
        }

        if (! $force && $this->hasValidToken($partner)) {
            return ['cached' => true, 'expires_at' => $partner->token_expires_at?->toIso8601String()];
        }

        $url = $this->resolveUrl($partner, (string) $partner->endpoint_login);
        $payload = $this->loginPayload($partner);
        $startedAt = microtime(true);
        $response = null;
        $error = null;

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $this->logExchange($partner, 'POST', $url, $this->maskLoginPayload($payload), $response, $error, 'AUTH', $startedAt);

        if ($error !== null) {
            throw new PartnerApiException("Login request failed: {$error}");
        }

        if ($response->failed()) {
            throw PartnerApiException::fromResponse($partner, $url, $response);
        }

        $body = $response->json() ?? [];

        if (array_key_exists('success', $body) && $body['success'] === false) {
            $message = is_string($body['message'] ?? null) ? $body['message'] : 'Login failed.';
            throw new PartnerApiException($message);
        }

        $token = $this->extractToken($body, $partner);

        if ($token === null) {
            $path = filled($partner->login_token_field) ? (string) $partner->login_token_field : 'data.token';
            throw new PartnerApiException("Login succeeded but no token was found at [{$path}].");
        }

        $expiresIn = $this->extractExpiresIn($body);
        $this->storeToken($partner, $token, $expiresIn);

        return [
            'cached' => false,
            'expires_at' => $partner->token_expires_at?->toIso8601String(),
            'token_preview' => Str::limit($token, 12, '…'),
        ];
    }

    public function ensureAuthenticated(Partner $partner): void
    {
        if (PartnerAuthType::resolve($partner->auth_type) !== PartnerAuthType::LOGIN_TOKEN) {
            return;
        }

        if (! $this->hasValidToken($partner)) {
            $this->login($partner);
        }
    }

    public function invalidateToken(Partner $partner): void
    {
        if ($partner->id) {
            $partner->forceFill([
                'access_token' => null,
                'token_expires_at' => null,
            ])->save();
        }

        unset($this->runtimeTokens[$this->tokenKey($partner)]);
    }

    private function applyBasic(PendingRequest $client, Partner $partner): PendingRequest
    {
        $secret = $partner->client_secret !== null ? (string) $partner->client_secret : null;

        if ($partner->client_id !== null && $secret !== null) {
            return $client->withBasicAuth($partner->client_id, $secret);
        }

        return $client;
    }

    private function applyBearer(PendingRequest $client, Partner $partner): PendingRequest
    {
        $token = $partner->client_secret !== null ? (string) $partner->client_secret : null;

        return $token ? $client->withToken($token) : $client;
    }

    private function applyApiKey(PendingRequest $client, Partner $partner): PendingRequest
    {
        $header = filled($partner->api_key_header) ? (string) $partner->api_key_header : 'X-API-Key';
        $key = $partner->client_secret !== null ? (string) $partner->client_secret : null;

        return $key ? $client->withHeaders([$header => $key]) : $client;
    }

    private function applyLoginToken(PendingRequest $client, Partner $partner): PendingRequest
    {
        $this->ensureAuthenticated($partner);

        $token = $this->resolveToken($partner);

        return $token ? $client->withToken($token) : $client;
    }

    private function hasValidToken(Partner $partner): bool
    {
        $token = $this->resolveToken($partner);

        if ($token === null) {
            return false;
        }

        if ($partner->token_expires_at === null) {
            return true;
        }

        return $partner->token_expires_at->isFuture();
    }

    private function resolveToken(Partner $partner): ?string
    {
        $key = $this->tokenKey($partner);

        if (isset($this->runtimeTokens[$key])) {
            return $this->runtimeTokens[$key];
        }

        return filled($partner->access_token) ? (string) $partner->access_token : null;
    }

    private function storeToken(Partner $partner, string $token, ?int $expiresIn): void
    {
        $expiresAt = now()->addSeconds($expiresIn ?? self::DEFAULT_TOKEN_TTL_SECONDS);
        $this->runtimeTokens[$this->tokenKey($partner)] = $token;

        $partner->access_token = $token;
        $partner->token_expires_at = $expiresAt;

        if ($partner->exists) {
            $partner->save();
        }
    }

    private function tokenKey(Partner $partner): int
    {
        return $partner->id ?? spl_object_id($partner);
    }

    /**
     * @return array<string, string>
     */
    private function loginPayload(Partner $partner): array
    {
        $usernameField = filled($partner->login_username_field) ? (string) $partner->login_username_field : 'email';
        $passwordField = filled($partner->login_password_field) ? (string) $partner->login_password_field : 'password';

        return array_filter([
            $usernameField => $partner->client_id,
            $passwordField => $partner->client_secret !== null ? (string) $partner->client_secret : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractToken(array $body, Partner $partner): ?string
    {
        $configuredPath = filled($partner->login_token_field) ? (string) $partner->login_token_field : 'data.token';
        $configured = Arr::get($body, $configuredPath);

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $fallbacks = [
            'data.token',
            'token',
            'access_token',
            'data.access_token',
            'data.api_token',
        ];

        foreach ($fallbacks as $path) {
            if ($path === $configuredPath) {
                continue;
            }

            $candidate = Arr::get($body, $path);

            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractExpiresIn(array $body): ?int
    {
        $value = Arr::get($body, 'expires_in') ?? Arr::get($body, 'data.expires_in');

        return is_numeric($value) ? (int) $value : null;
    }

    public function resolveUrl(Partner $partner, string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        return rtrim((string) $partner->api_base_url, '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     */
    public function logExchange(
        Partner $partner,
        string $method,
        string $endpoint,
        array $requestPayload,
        ?Response $response,
        ?string $error,
        string $action = 'API',
        ?float $startedAt = null,
    ): void {
        try {
            ApiLog::create([
                'partner_id' => $partner->id,
                'action' => $action,
                'method' => strtoupper($method),
                'endpoint' => $endpoint,
                'request_payload' => $this->maskPayload($requestPayload !== [] ? $requestPayload : null),
                'response_payload' => $this->maskResponsePayload($response),
                'status_code' => $response?->status(),
                'error_message' => $error,
                'duration_ms' => $startedAt ? (int) round((microtime(true) - $startedAt) * 1000) : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to persist partner API log.', [
                'partner_id' => $partner->id,
                'endpoint' => $endpoint,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function maskLoginPayload(array $payload): array
    {
        return array_map(static fn () => '***', $payload);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function maskPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $masked = [];
        $sensitiveKeys = [
            'password',
            'client_secret',
            'secret',
            'secret_key',
            'public_key',
            'token',
            'access_token',
            'api_key',
            'api_token',
        ];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $masked[$key] = '***';
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskPayload($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function maskResponsePayload(?Response $response): ?array
    {
        if ($response === null) {
            return null;
        }

        $json = $response->json();

        if (! is_array($json)) {
            return ['raw' => Str::limit($response->body(), 2000)];
        }

        return $this->maskPayload($json);
    }
}
