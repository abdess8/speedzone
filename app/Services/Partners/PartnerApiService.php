<?php

namespace App\Services\Partners;

use App\Enums\PartnerAuthType;
use App\Models\Partner;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin HTTP gateway to a B2B partner's delivery API.
 */
class PartnerApiService
{
    public function __construct(
        private readonly PartnerAuthenticator $authenticator,
        private readonly int $timeoutSeconds = 30,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getAllStatuses(Partner $partner): array
    {
        return $this->request($partner, 'GET', $this->endpoint($partner, 'endpoint_statuses', '/all-status-deliveries'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getDeliveries(Partner $partner, int $page = 1): array
    {
        return $this->request($partner, 'GET', $this->endpoint($partner, 'endpoint_deliveries', '/deliveries'), [
            'page' => $page,
        ]);
    }

    /**
     * Fetch a single delivery from the partner API using the configured lookup parameter.
     *
     * @return array<string, mixed>
     *
     * @throws PartnerApiException
     */
    public function getDeliveryByCode(Partner $partner, string $code): array
    {
        $param = trim((string) $partner->delivery_lookup_param);

        if ($param === '') {
            throw new PartnerApiException('Single delivery lookup parameter is not configured on this partner.');
        }

        return $this->request(
            $partner,
            'GET',
            $this->endpoint($partner, 'endpoint_deliveries', '/deliveries'),
            [$param => $code]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function pushStatusUpdate(Partner $partner, array $payload): array
    {
        return $this->request($partner, 'POST', $this->endpoint($partner, 'endpoint_update', '/update-deliveries'), $payload);
    }

    /**
     * Login (if needed) then call the statuses endpoint to verify connectivity.
     *
     * @return array<string, mixed>
     *
     * @throws PartnerApiException
     */
    public function testConnection(Partner $partner): array
    {
        if (blank($partner->api_base_url) && blank($partner->endpoint_login)) {
            throw new PartnerApiException('API base URL or login endpoint must be configured.');
        }

        $result = [];

        if (PartnerAuthType::resolve($partner->auth_type) === PartnerAuthType::LOGIN_TOKEN) {
            $result['login'] = $this->authenticator->login($partner, force: true);
            $result['deliveries'] = $this->getDeliveries($partner, page: 1);

            return $result;
        }

        $result['statuses'] = $this->getAllStatuses($partner);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws PartnerApiException
     */
    private function request(Partner $partner, string $method, string $endpoint, array $data = [], bool $isRetry = false): array
    {
        $method = strtoupper($method);
        $url = $this->authenticator->resolveUrl($partner, $endpoint);
        $response = null;
        $error = null;
        $startedAt = microtime(true);

        try {
            $client = $this->httpClient($partner);

            $response = match ($method) {
                'GET' => $client->get($url, $data),
                'POST' => $client->post($url, $data),
                'PUT' => $client->put($url, $data),
                'PATCH' => $client->patch($url, $data),
                'DELETE' => $client->delete($url, $data),
                default => throw new \InvalidArgumentException("Unsupported HTTP method [{$method}]."),
            };
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $this->authenticator->logExchange($partner, $method, $url, $data, $response, $error, 'API', $startedAt);

        if ($error !== null) {
            throw new PartnerApiException(
                "Partner [{$partner->name}] request to {$endpoint} failed: {$error}"
            );
        }

        if (
            ! $isRetry
            && $response->status() === 401
            && PartnerAuthType::resolve($partner->auth_type) === PartnerAuthType::LOGIN_TOKEN
        ) {
            $this->authenticator->invalidateToken($partner);
            $this->authenticator->login($partner, force: true);

            return $this->request($partner, $method, $endpoint, $data, true);
        }

        if ($response->failed()) {
            throw PartnerApiException::fromResponse($partner, $endpoint, $response);
        }

        return $response->json() ?? [];
    }

    private function endpoint(Partner $partner, string $field, string $default): string
    {
        $value = $partner->{$field};

        return filled($value) ? (string) $value : $default;
    }

    private function httpClient(Partner $partner): PendingRequest
    {
        $client = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson();

        return $this->authenticator->apply($client, $partner);
    }
}
