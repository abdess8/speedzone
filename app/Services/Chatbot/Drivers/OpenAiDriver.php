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
 * OpenAI Chat Completions.
 *
 * Kept alongside the Gemini driver so the provider stays a configuration
 * choice: the tool catalogue, the policies and the widget are identical either
 * way, and switching back is one line in .env.
 */
class OpenAiDriver implements ChatDriver
{
    public function name(): string
    {
        return 'openai';
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
            'model' => $this->config('model'),
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $this->messages($turns),
            ),
            'temperature' => (float) $this->config('temperature', 0.2),
            'max_tokens' => (int) $this->config('max_tokens', 900),
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(fn (array $tool) => [
                'type' => 'function',
                'function' => $tool,
            ], $tools);
            $payload['tool_choice'] = 'auto';
            // Several tools may be needed for one question, but running them
            // one round at a time lets the model react to a failure instead of
            // piling on.
            $payload['parallel_tool_calls'] = false;
        }

        return $this->parse($this->send($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(array $payload): array
    {
        try {
            $response = Http::withToken((string) $this->config('api_key'))
                ->withHeaders(array_filter([
                    'OpenAI-Organization' => $this->config('organization'),
                    'OpenAI-Project' => $this->config('project'),
                ], fn ($value) => filled($value)))
                ->baseUrl(rtrim((string) $this->config('base_url'), '/'))
                ->timeout((int) config('ai.chatbot.timeout', 45))
                ->retry(2, 400, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->acceptJson()
                ->asJson()
                ->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            Log::warning('Chatbot: OpenAI connection failed', ['message' => $e->getMessage()]);

            throw new ChatDriverException('Could not reach the OpenAI API.', 0, $e);
        }

        if ($response->failed()) {
            $reason = (string) ($response->json('error.message') ?? $response->reason());

            Log::warning('Chatbot: OpenAI request rejected', [
                'status' => $response->status(),
                'reason' => $reason,
            ]);

            if ($response->status() === 429) {
                $retryAfter = $response->header('retry-after');

                throw new RateLimitedException($reason, is_numeric($retryAfter) ? (int) $retryAfter : null);
            }

            throw ChatDriverException::requestFailed($this->name(), $response->status(), $reason);
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['choices'][0])) {
            throw ChatDriverException::unexpectedPayload($this->name());
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parse(array $data): ChatCompletion
    {
        $message = $data['choices'][0]['message'] ?? [];

        $toolCalls = [];

        foreach ($message['tool_calls'] ?? [] as $call) {
            $arguments = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);

            $toolCalls[] = new ToolCall(
                id: (string) ($call['id'] ?? Str::uuid()->toString()),
                name: (string) ($call['function']['name'] ?? ''),
                arguments: is_array($arguments) ? $arguments : [],
            );
        }

        return new ChatCompletion(
            text: filled($message['content'] ?? null) ? (string) $message['content'] : null,
            toolCalls: $toolCalls,
            raw: $message,
        );
    }

    /**
     * Neutral turns → OpenAI `messages`.
     *
     * @param  array<int, array<string, mixed>>  $turns
     * @return array<int, array<string, mixed>>
     */
    private function messages(array $turns): array
    {
        $messages = [];

        foreach ($turns as $turn) {
            $messages[] = match ($turn['role']) {
                'user' => ['role' => 'user', 'content' => (string) $turn['text']],
                'assistant' => $this->assistantMessage($turn),
                'tool' => [
                    'role' => 'tool',
                    'tool_call_id' => (string) $turn['id'],
                    'content' => json_encode($turn['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
                default => null,
            };
        }

        return array_values(array_filter($messages));
    }

    /**
     * @param  array<string, mixed>  $turn
     * @return array<string, mixed>
     */
    private function assistantMessage(array $turn): array
    {
        if (is_array($turn['raw'] ?? null)) {
            return $turn['raw'];
        }

        $message = ['role' => 'assistant', 'content' => $turn['text'] ?? null];

        if (($turn['tool_calls'] ?? []) !== []) {
            $message['tool_calls'] = array_map(fn (ToolCall $call) => [
                'id' => $call->id,
                'type' => 'function',
                'function' => [
                    'name' => $call->name,
                    'arguments' => json_encode($call->arguments, JSON_UNESCAPED_UNICODE),
                ],
            ], $turn['tool_calls']);
        }

        return $message;
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("ai.providers.openai.{$key}", $default);
    }
}
