<?php

namespace App\Services\Chatbot\Tools;

use App\Services\Chatbot\Contracts\ChatbotTool;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Shared plumbing for tools: argument validation and JSON Schema assembly.
 *
 * Model-generated arguments are treated exactly like request input — they go
 * through the validator before a tool touches anything. Failures bubble up as
 * ValidationException and are turned into a readable refusal by the caller.
 */
abstract class AbstractChatbotTool implements ChatbotTool
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validate(array $arguments, array $rules): array
    {
        return Validator::make($arguments, $rules)->validate();
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  array<int, string>  $required
     * @return array<string, mixed>
     */
    protected function schema(array $properties, array $required = []): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
