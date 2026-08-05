<?php

namespace App\Services\Chatbot;

use App\Models\User;
use App\Services\Chatbot\Contracts\ChatbotTool;
use App\Services\Chatbot\Tools\ChangeOrderStatusTool;
use App\Services\Chatbot\Tools\GenerateInvoicePdfTool;
use App\Services\Chatbot\Tools\GetDeliveryKpisTool;
use App\Services\Chatbot\Tools\SearchEntitiesTool;

/**
 * The catalogue of capabilities the assistant may call.
 *
 * Registration is explicit rather than auto-discovered: a tool is a piece of
 * the application an LLM is allowed to invoke, and that list should only ever
 * grow through a deliberate edit here.
 */
class ChatbotToolRegistry
{
    /**
     * @var array<int, class-string<ChatbotTool>>
     */
    private const TOOLS = [
        ChangeOrderStatusTool::class,
        GenerateInvoicePdfTool::class,
        SearchEntitiesTool::class,
        GetDeliveryKpisTool::class,
    ];

    /**
     * Tools this user may actually call, keyed by function name.
     *
     * Withholding a tool the user has no permission for is both a safety net
     * and a prompt optimisation: the model cannot offer an action that would
     * only be refused downstream.
     *
     * @return array<string, ChatbotTool>
     */
    public function availableFor(User $user): array
    {
        $available = [];

        foreach (self::TOOLS as $class) {
            /** @var ChatbotTool $tool */
            $tool = app($class);

            if ($tool->isAvailableFor($user)) {
                $available[$tool->name()] = $tool;
            }
        }

        return $available;
    }

    /**
     * Provider-neutral function declarations for the given tools.
     *
     * Wrapping these into OpenAI's `{type: function, function: {...}}` or
     * Gemini's `functionDeclarations` is each driver's job.
     *
     * @param  array<string, ChatbotTool>  $tools
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function declarations(array $tools): array
    {
        return array_values(array_map(fn (ChatbotTool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => $tool->parameters(),
        ], $tools));
    }
}
