<?php

namespace App\Services\Partners;

use App\Models\Partner;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Raised when a partner API call cannot be completed: a transport error
 * (timeout / connection refused) or a non-2xx HTTP response.
 */
class PartnerApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $responseBody
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?array $responseBody = null,
    ) {
        parent::__construct($message, $statusCode ?? 0);
    }

    public static function fromResponse(Partner $partner, string $endpoint, Response $response): self
    {
        return new self(
            "Partner [{$partner->name}] API returned HTTP {$response->status()} for {$endpoint}.",
            $response->status(),
            $response->json(),
        );
    }

    /**
     * Whether the failure is a server-side error worth retrying (5xx).
     */
    public function isServerError(): bool
    {
        return $this->statusCode !== null && $this->statusCode >= 500;
    }

    /**
     * Whether the failure is a transport-level error (no HTTP response).
     */
    public function isConnectionError(): bool
    {
        return $this->statusCode === null;
    }
}
