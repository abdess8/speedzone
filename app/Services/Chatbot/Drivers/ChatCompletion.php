<?php

namespace App\Services\Chatbot\Drivers;

/**
 * One model answer, normalised across providers.
 *
 * `raw` is the provider's own representation of this same turn, kept so it can
 * be replayed verbatim on the next round-trip. That matters more than it looks:
 * Gemini attaches an opaque thought signature to the parts of a turn that
 * requested a tool, and rebuilding the turn from `text` and `toolCalls` would
 * silently drop it and break the model's reasoning chain.
 */
final class ChatCompletion
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $toolCalls = [],
        public readonly ?array $raw = null,
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}
