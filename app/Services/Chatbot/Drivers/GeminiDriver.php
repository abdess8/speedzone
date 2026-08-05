<?php

namespace App\Services\Chatbot\Drivers;

use App\Services\Chatbot\ChatDriverException;
use App\Services\Chatbot\Contracts\ChatDriver;
use App\Services\Chatbot\RateLimitedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Google Gemini, through the native generateContent endpoint.
 *
 * Gemini also ships an OpenAI-compatible endpoint, which would have made this
 * a one-line base-URL swap. It is deliberately not used: Google's current
 * "AQ."-prefixed AI Studio keys are rejected there ("Multiple authentication
 * credentials received") because the compat layer derives a second credential
 * from the token it receives via Bearer auth. The native endpoint takes the key
 * in `x-goog-api-key` and accepts both the new and the legacy key format.
 */
class GeminiDriver implements ChatDriver
{
    /**
     * Schema keywords Gemini's OpenAPI subset understands. Anything else — most
     * notably `additionalProperties`, which our tool schemas set — is rejected
     * with a 400, so the declarations are filtered before they are sent.
     */
    private const SUPPORTED_SCHEMA_KEYS = [
        'type', 'nullable', 'required', 'format', 'description',
        'properties', 'items', 'enum', 'anyOf', '$ref', '$defs',
    ];

    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled($this->config('api_key'));
    }

    public function chat(string $systemPrompt, array $turns, array $tools = []): ChatCompletion
    {
        if (! $this->isConfigured()) {
            throw ChatDriverException::missingApiKey($this->name());
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $this->contents($turns),
            'generationConfig' => array_filter([
                'temperature' => (float) $this->config('temperature', 0.2),
                'maxOutputTokens' => (int) $this->config('max_tokens', 2048),
                'thinkingConfig' => filled($this->config('thinking_level'))
                    ? ['thinkingLevel' => $this->config('thinking_level')]
                    : null,
            ], fn ($value) => $value !== null),
        ];

        if ($tools !== []) {
            $payload['tools'] = [['functionDeclarations' => $this->declarations($tools)]];
            $payload['toolConfig'] = ['functionCallingConfig' => ['mode' => 'AUTO']];
        }

        return $this->parse($this->send($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(array $payload): array
    {
        $model = (string) $this->config('model');
        $baseUrl = rtrim((string) $this->config('base_url'), '/');

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) $this->config('api_key')])
                ->timeout((int) config('ai.chatbot.timeout', 45))
                ->retry(2, 400, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->acceptJson()
                ->asJson()
                ->post("{$baseUrl}/models/{$model}:generateContent", $payload);
        } catch (ConnectionException $e) {
            Log::warning('Chatbot: Gemini connection failed', ['message' => $e->getMessage()]);

            throw new ChatDriverException('Could not reach the Gemini API.', 0, $e);
        }

        if ($response->failed()) {
            $reason = (string) ($response->json('error.message') ?? $response->reason());

            Log::warning('Chatbot: Gemini request rejected', [
                'status' => $response->status(),
                'reason' => $reason,
            ]);

            if ($response->status() === 429) {
                throw new RateLimitedException($reason, $this->retryAfter($response->json()));
            }

            throw ChatDriverException::requestFailed($this->name(), $response->status(), $reason);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw ChatDriverException::unexpectedPayload($this->name());
        }

        return $data;
    }

    /**
     * Seconds Google asks us to wait, from the RetryInfo detail it attaches to
     * quota errors (e.g. "25.268s"). Rounded up so we never retry too early.
     *
     * @param  mixed  $body
     */
    private function retryAfter($body): ?int
    {
        if (! is_array($body)) {
            return null;
        }

        foreach ($body['error']['details'] ?? [] as $detail) {
            if (isset($detail['retryDelay']) && preg_match('/([\d.]+)s/', (string) $detail['retryDelay'], $m)) {
                return (int) ceil((float) $m[1]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parse(array $data): ChatCompletion
    {
        // A prompt blocked upstream comes back with no candidate at all.
        if (isset($data['promptFeedback']['blockReason'])) {
            throw new ChatDriverException(
                'Gemini blocked the prompt: '.$data['promptFeedback']['blockReason']
            );
        }

        $content = $data['candidates'][0]['content'] ?? null;

        if (! is_array($content)) {
            $finish = (string) ($data['candidates'][0]['finishReason'] ?? 'unknown');

            throw new ChatDriverException("Gemini returned no content (finishReason: {$finish}).");
        }

        $text = '';
        $toolCalls = [];

        foreach ($content['parts'] ?? [] as $part) {
            // Thought parts are the model reasoning out loud; they are billed
            // and returned but must never reach the user.
            if (($part['thought'] ?? false) === true) {
                continue;
            }

            if (isset($part['text'])) {
                $text .= $part['text'];
            }

            if (isset($part['functionCall'])) {
                $call = $part['functionCall'];

                $toolCalls[] = new ToolCall(
                    id: (string) ($call['id'] ?? Str::uuid()->toString()),
                    name: (string) ($call['name'] ?? ''),
                    arguments: is_array($call['args'] ?? null) ? $call['args'] : [],
                );
            }
        }

        return new ChatCompletion(
            text: $text !== '' ? $text : null,
            toolCalls: $toolCalls,
            raw: $content,
        );
    }

    /**
     * Neutral turns → Gemini `contents`.
     *
     * @param  array<int, array<string, mixed>>  $turns
     * @return array<int, array<string, mixed>>
     */
    private function contents(array $turns): array
    {
        $contents = [];

        foreach ($turns as $turn) {
            $contents[] = match ($turn['role']) {
                'user' => ['role' => 'user', 'parts' => [['text' => (string) $turn['text']]]],
                'assistant' => $this->assistantContent($turn),
                // Gemini's Content.role only accepts "user" or "model", so a
                // tool result is delivered as a user turn carrying a
                // functionResponse part rather than a role of its own.
                'tool' => ['role' => 'user', 'parts' => [[
                    'functionResponse' => [
                        'id' => (string) $turn['id'],
                        'name' => (string) $turn['name'],
                        // `response` must be a JSON object, never a scalar.
                        'response' => (object) $turn['result'],
                    ],
                ]]],
                default => null,
            };
        }

        return array_values(array_filter($contents));
    }

    /**
     * @param  array<string, mixed>  $turn
     * @return array<string, mixed>
     */
    private function assistantContent(array $turn): array
    {
        // Replayed verbatim when we have it, so the thought signature Gemini
        // attached to the tool-calling parts survives the round-trip.
        if (is_array($turn['raw'] ?? null)) {
            return $turn['raw'];
        }

        $parts = [];

        if (filled($turn['text'] ?? null)) {
            $parts[] = ['text' => (string) $turn['text']];
        }

        foreach ($turn['tool_calls'] ?? [] as $call) {
            $parts[] = ['functionCall' => [
                'id' => $call->id,
                'name' => $call->name,
                'args' => (object) $call->arguments,
            ]];
        }

        return ['role' => 'model', 'parts' => $parts !== [] ? $parts : [['text' => '']]];
    }

    /**
     * @param  array<int, array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     * @return array<int, array<string, mixed>>
     */
    private function declarations(array $tools): array
    {
        return array_map(function (array $tool): array {
            $parameters = $this->sanitiseSchema($tool['parameters']);

            return array_filter([
                'name' => $tool['name'],
                'description' => $tool['description'],
                // A function with no arguments must omit `parameters` entirely
                // rather than send an empty object.
                'parameters' => ($parameters['properties'] ?? []) !== [] ? $parameters : null,
            ], fn ($value) => $value !== null);
        }, $tools);
    }

    /**
     * Recursively drop schema keywords Gemini does not accept.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function sanitiseSchema(array $schema): array
    {
        $clean = [];

        foreach ($schema as $key => $value) {
            if (! in_array($key, self::SUPPORTED_SCHEMA_KEYS, true)) {
                continue;
            }

            $clean[$key] = match (true) {
                $key === 'properties' && is_array($value) => array_map(
                    fn ($property) => is_array($property) ? $this->sanitiseSchema($property) : $property,
                    $value
                ),
                $key === 'items' && is_array($value) => $this->sanitiseSchema($value),
                $key === 'anyOf' && is_array($value) => array_map(
                    fn ($branch) => is_array($branch) ? $this->sanitiseSchema($branch) : $branch,
                    $value
                ),
                default => $value,
            };
        }

        return $clean;
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("ai.providers.gemini.{$key}", $default);
    }
}
