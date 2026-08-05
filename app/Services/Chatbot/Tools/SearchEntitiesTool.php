<?php

namespace App\Services\Chatbot\Tools;

use App\Models\User;
use App\Services\Chatbot\Support\ChatbotSearchService;
use App\Services\Chatbot\ToolResult;
use Illuminate\Validation\Rule;

/**
 * Free-text lookup across orders, drivers, sellers and customers.
 *
 * Read-only, and scoped: every result set is filtered by the acting user's own
 * read permissions, so a driver searching "Casablanca" sees his own runs and a
 * seller sees only his shop.
 */
class SearchEntitiesTool extends AbstractChatbotTool
{
    private const TYPES = ['all', 'orders', 'drivers', 'sellers', 'customers'];

    public function __construct(private readonly ChatbotSearchService $search) {}

    public function name(): string
    {
        return 'searchEntities';
    }

    public function description(): string
    {
        return 'Search orders (tracking number, customer name, phone, city), drivers, sellers '
            .'and customers. Use it to resolve a vague reference before acting on it, and to '
            .'answer "find/show me…" questions. Results are already limited to what the '
            .'current user is allowed to see.';
    }

    public function parameters(): array
    {
        return $this->schema([
            'query' => [
                'type' => 'string',
                'description' => 'Free text: tracking number, name, phone number or city.',
            ],
            'type' => [
                'type' => 'string',
                'enum' => self::TYPES,
                'description' => 'Restrict the search to one entity type. Defaults to "all".',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum results per entity type (1-20, default 5).',
            ],
        ], ['query']);
    }

    public function isAvailableFor(User $user): bool
    {
        return $user->hasPermission('orders.read.all')
            || $user->hasPermission('orders.read.own')
            || $user->hasPermission('orders.read.assigned')
            || $user->hasPermission('users.read');
    }

    public function execute(array $arguments, User $user): ToolResult
    {
        $input = $this->validate($arguments, [
            'query' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['nullable', 'string', Rule::in(self::TYPES)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $results = $this->search->run(
            $user,
            $input['query'],
            $input['type'] ?? 'all',
            (int) ($input['limit'] ?? 5),
        );

        $total = array_sum(array_map('count', $results));

        if ($total === 0) {
            return ToolResult::failure('no_match', ['query' => $input['query']]);
        }

        return ToolResult::success(
            modelPayload: [
                'query' => $input['query'],
                'total' => $total,
                'results' => $results,
            ],
            actionType: 'search_results',
            actionData: [
                'query' => $input['query'],
                'total' => $total,
                'results' => $results,
            ],
        );
    }
}
