<?php

namespace App\Services\Chatbot\Contracts;

use App\Services\Chatbot\ChatDriverException;
use App\Services\Chatbot\Drivers\ChatCompletion;

/**
 * A tool-calling chat provider.
 *
 * The conversation handed to a driver is provider-neutral: a system prompt, a
 * list of turns, and the tool catalogue. Translating that into Gemini's
 * `contents`/`parts` or OpenAI's `messages`/`tool_calls` is the driver's whole
 * job, which is what lets the tools, the policies and the widget stay
 * completely unaware of which provider is answering.
 *
 * Turn shapes:
 *   ['role' => 'user',      'text' => string]
 *   ['role' => 'assistant', 'text' => ?string, 'tool_calls' => ToolCall[], 'raw' => ?array]
 *   ['role' => 'tool',      'id' => string, 'name' => string, 'result' => array]
 */
interface ChatDriver
{
    /**
     * Whether the provider has the credentials it needs to answer.
     */
    public function isConfigured(): bool;

    /**
     * Name of the provider, for logs and diagnostics.
     */
    public function name(): string;

    /**
     * @param  array<int, array<string, mixed>>  $turns
     * @param  array<int, array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     *
     * @throws ChatDriverException
     */
    public function chat(string $systemPrompt, array $turns, array $tools = []): ChatCompletion;
}
