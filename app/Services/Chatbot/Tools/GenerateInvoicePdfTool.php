<?php

namespace App\Services\Chatbot\Tools;

use App\Models\Order;
use App\Models\User;
use App\Services\Chatbot\Support\OrderLocator;
use App\Services\Chatbot\ToolResult;
use Illuminate\Support\Facades\Gate;

/**
 * Produces a downloadable PDF for one order.
 *
 * Two documents can answer "send me the invoice for #1024", and the right one
 * depends on whether the order has been settled:
 *
 *  - settled → the real seller invoice it was billed on, served by the existing
 *    invoice route so the accounting document stays the single source of truth;
 *  - not settled → a single-order statement generated on the fly.
 *
 * Either way the tool returns a URL rather than bytes. The download is a second
 * authenticated request that re-runs the policy, so a link leaked out of the
 * transcript is worthless to anyone else.
 */
class GenerateInvoicePdfTool extends AbstractChatbotTool
{
    public function __construct(private readonly OrderLocator $locator) {}

    public function name(): string
    {
        return 'generateInvoicePdf';
    }

    public function description(): string
    {
        return 'Produce a downloadable PDF invoice for one order, identified by its tracking '
            .'number or numeric id. Returns a download URL — present it to the user, never '
            .'try to render the document yourself.';
    }

    public function parameters(): array
    {
        return $this->schema([
            'order_id' => [
                'type' => 'string',
                'description' => 'Tracking number or numeric id of the order, without the leading "#".',
            ],
        ], ['order_id']);
    }

    public function isAvailableFor(User $user): bool
    {
        return $user->hasPermission('orders.print')
            || $user->hasPermission('invoices.print')
            || $user->hasPermission('invoices.read.own')
            || $user->hasPermission('invoices.read.all');
    }

    public function execute(array $arguments, User $user): ToolResult
    {
        $input = $this->validate($arguments, [
            'order_id' => ['required', 'string', 'max:64'],
        ]);

        $order = $this->locator->find($input['order_id'], $user);

        if (! $order) {
            return ToolResult::failure('order_not_found', ['order_id' => $input['order_id']]);
        }

        $document = $this->resolveDocument($order, $user);

        if ($document === null) {
            return ToolResult::failure('not_authorized_to_print', [
                'tracking_number' => $order->tracking_number,
            ]);
        }

        return ToolResult::success(
            modelPayload: [
                'tracking_number' => $order->tracking_number,
                'document_kind' => $document['kind'],
                'reference' => $document['reference'],
                'download_available' => true,
            ],
            actionType: 'invoice_ready',
            actionData: array_merge($document, ['order' => OrderLocator::summarise($order, $user)]),
        );
    }

    /**
     * @return array{kind: string, reference: string, download_url: string, file_name: string, amount: float|null}|null
     */
    private function resolveDocument(Order $order, User $user): ?array
    {
        $invoice = $order->invoice;

        if ($invoice && Gate::forUser($user)->allows('print', $invoice)) {
            return [
                'kind' => 'seller_invoice',
                'reference' => $invoice->invoice_number,
                'download_url' => route('invoices.pdf', ['invoice' => $invoice->id, 'download' => 1]),
                'file_name' => $invoice->invoice_number.'.pdf',
                'amount' => (float) $invoice->net_amount,
            ];
        }

        if (! Gate::forUser($user)->allows('print', $order)) {
            return null;
        }

        return [
            'kind' => 'order_statement',
            'reference' => $order->tracking_number,
            'download_url' => route('api.chatbot.orders.invoice', ['order' => $order->id, 'download' => 1]),
            'file_name' => $order->tracking_number.'.pdf',
            'amount' => (float) $order->total_amount,
        ];
    }
}
