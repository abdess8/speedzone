<?php

namespace App\Services\Chatbot\Tools;

use App\Enums\OrderFailureReason;
use App\Enums\OrderStatus;
use App\Models\User;
use App\Services\Chatbot\Support\OrderLocator;
use App\Services\Chatbot\ToolResult;
use App\Services\OrderTransitionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Moves an order through the delivery workflow.
 *
 * The tool is a thin adapter: the transition graph, the per-target RBAC gates,
 * the driver payout and the partner sync all stay inside
 * {@see OrderTransitionService}, which is the same path the REST API and the
 * bulk screens take. The assistant therefore cannot perform a transition a
 * human could not perform from the UI.
 */
class ChangeOrderStatusTool extends AbstractChatbotTool
{
    public function __construct(
        private readonly OrderLocator $locator,
        private readonly OrderTransitionService $transitions,
    ) {}

    public function name(): string
    {
        return 'changeOrderStatus';
    }

    public function description(): string
    {
        return 'Change the delivery status of one order, identified by its tracking number '
            .'(e.g. SPD-2026-000045) or its numeric id. Only transitions allowed by the '
            .'workflow succeed; when the call is refused, explain the reason to the user '
            .'instead of retrying. Moving an order to FAILED requires a failure_reason.';
    }

    public function parameters(): array
    {
        return $this->schema([
            'order_id' => [
                'type' => 'string',
                'description' => 'Tracking number or numeric id of the order, without the leading "#".',
            ],
            'status' => [
                'type' => 'string',
                'enum' => OrderStatus::values(),
                'description' => 'Target status.',
            ],
            'comment' => [
                'type' => 'string',
                'description' => 'Optional note stored in the order timeline.',
            ],
            'failure_reason' => [
                'type' => 'string',
                'enum' => OrderFailureReason::values(),
                'description' => 'Required when status is FAILED.',
            ],
        ], ['order_id', 'status']);
    }

    public function isAvailableFor(User $user): bool
    {
        return $user->hasPermission('orders.update.all')
            || $user->hasPermission('orders.update.own')
            || $user->hasPermission('orders.update.assigned');
    }

    public function execute(array $arguments, User $user): ToolResult
    {
        $input = $this->validate($arguments, [
            'order_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', Rule::in(OrderStatus::values())],
            'comment' => ['nullable', 'string', 'max:500'],
            'failure_reason' => ['nullable', 'string', Rule::in(OrderFailureReason::values())],
        ]);

        $order = $this->locator->find($input['order_id'], $user);

        if (! $order) {
            return ToolResult::failure('order_not_found', ['order_id' => $input['order_id']]);
        }

        if (! Gate::forUser($user)->allows('updateStatus', $order)) {
            return ToolResult::failure('not_authorized_for_this_order', [
                'tracking_number' => $order->tracking_number,
            ]);
        }

        $previous = OrderLocator::summarise($order, $user);
        $target = OrderStatus::from($input['status']);

        if ($previous['status'] === $target->value) {
            return ToolResult::failure('already_in_target_status', [
                'tracking_number' => $order->tracking_number,
                'status' => $target->value,
            ]);
        }

        if (! in_array($target->value, $this->transitions->allowedNextStatuses($order), true)) {
            return ToolResult::failure('transition_not_allowed', [
                'tracking_number' => $order->tracking_number,
                'current_status' => $previous['status'],
                'requested_status' => $target->value,
                'allowed_next_statuses' => $this->transitions->allowedNextStatuses($order),
            ]);
        }

        $order = $this->transitions->transition(
            $order,
            $target->value,
            $user,
            $input['comment'] ?? null,
            ['failure_reason' => $input['failure_reason'] ?? null],
        );

        $summary = OrderLocator::summarise($order->load(['city', 'seller', 'driver']), $user);

        return ToolResult::success(
            modelPayload: [
                'tracking_number' => $summary['tracking_number'],
                'previous_status' => $previous['status'],
                'new_status' => $summary['status'],
                'new_status_label' => $summary['status_label'],
            ],
            actionType: 'order_status_changed',
            actionData: [
                'order' => $summary,
                'previous_status' => $previous['status'],
                'previous_status_label' => $previous['status_label'],
                'previous_status_color' => $previous['status_color'],
            ],
            mutatesData: true,
        );
    }
}
