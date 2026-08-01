<?php

namespace App\Services\Chatbot;

use RuntimeException;

/**
 * Raised when the AI provider is unreachable, misconfigured or answers with a
 * payload we cannot interpret. Never carries the API key or the raw prompt.
 */
class ChatDriverException extends RuntimeException
{
    public static function missingApiKey(string $provider): self
    {
        return new self("No API key configured for the \"{$provider}\" AI provider.");
    }

    public static function requestFailed(string $provider, int $status, string $reason): self
    {
        return new self("{$provider} request failed with status {$status}: {$reason}");
    }

    public static function unexpectedPayload(string $provider): self
    {
        return new self("{$provider} returned a response we could not interpret.");
    }

    public static function unknownDriver(string $driver): self
    {
        return new self("Unknown AI driver \"{$driver}\". Set AI_DRIVER to gemini or openai.");
    }
}
