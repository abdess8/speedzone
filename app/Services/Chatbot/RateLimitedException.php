<?php

namespace App\Services\Chatbot;

/**
 * The AI provider refused the call because its own quota is exhausted.
 *
 * Distinct from a generic failure because the user can act on it: waiting a
 * moment actually fixes it. Gemini's free tier in particular allows only a
 * handful of requests per minute, and one assistant message can spend several
 * of them when tools are involved.
 */
class RateLimitedException extends ChatDriverException
{
    public function __construct(string $message, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
