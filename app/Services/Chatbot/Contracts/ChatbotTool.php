<?php

namespace App\Services\Chatbot\Contracts;

use App\Models\User;
use App\Services\Chatbot\ToolResult;

/**
 * A single capability exposed to the model through OpenAI function calling.
 *
 * The model only ever sees {@see self::name()}, {@see self::description()} and
 * {@see self::parameters()}. It never reaches the database directly: every
 * implementation re-validates its arguments and re-authorises the acting user,
 * so a hallucinated or adversarial tool call is rejected exactly like a forged
 * HTTP request would be.
 */
interface ChatbotTool
{
    /**
     * Function name advertised to OpenAI (must match ^[a-zA-Z0-9_-]{1,64}$).
     */
    public function name(): string;

    /**
     * What the tool does, written for the model rather than for a developer.
     */
    public function description(): string;

    /**
     * JSON Schema of the accepted arguments.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Whether this user may use the tool at all. Tools they cannot use are
     * withheld from the schema, so the model never offers them in the first
     * place.
     */
    public function isAvailableFor(User $user): bool;

    /**
     * @param  array<string, mixed>  $arguments  Raw, model-generated arguments.
     */
    public function execute(array $arguments, User $user): ToolResult;
}
