<?php

namespace App\Services\Chatbot;

/**
 * Outcome of one tool invocation.
 *
 * It carries two distinct payloads on purpose:
 *
 *  - `modelPayload` is fed back to the model so it can phrase an answer. It is
 *    kept compact and free of anything the user is not allowed to read.
 *  - `action` is handed to the Vue widget so it can render a real UI affordance
 *    (a status badge, a download button, a KPI list) instead of a wall of text.
 */
final class ToolResult
{
    /**
     * @param  array<string, mixed>  $modelPayload
     * @param  array<string, mixed>|null  $actionData
     */
    private function __construct(
        public readonly bool $success,
        public readonly array $modelPayload,
        public readonly ?string $actionType = null,
        public readonly ?array $actionData = null,
        public readonly bool $mutatesData = false,
    ) {}

    /**
     * @param  array<string, mixed>  $modelPayload
     * @param  array<string, mixed>|null  $actionData
     */
    public static function success(
        array $modelPayload,
        ?string $actionType = null,
        ?array $actionData = null,
        bool $mutatesData = false,
    ): self {
        return new self(true, $modelPayload, $actionType, $actionData, $mutatesData);
    }

    /**
     * A refusal is not an exception: the model must be told why so it can
     * explain it to the user in their own language.
     *
     * @param  array<string, mixed>  $context
     */
    public static function failure(string $reason, array $context = []): self
    {
        return new self(false, array_merge(['error' => $reason], $context));
    }

    /**
     * @return array<string, mixed>
     */
    public function forModel(): array
    {
        return array_merge(['ok' => $this->success], $this->modelPayload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forFrontend(): ?array
    {
        if (! $this->success || $this->actionType === null) {
            return null;
        }

        return [
            'type' => $this->actionType,
            'data' => $this->actionData ?? [],
        ];
    }
}
