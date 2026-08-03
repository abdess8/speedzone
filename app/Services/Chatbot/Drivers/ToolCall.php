<?php

namespace App\Services\Chatbot\Drivers;

/**
 * A tool the model asked us to run.
 *
 * The id is echoed back with the result. OpenAI requires it to correlate the
 * response with the call; Gemini 3 issues one per call for the same reason.
 */
final class ToolCall
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {}
}
