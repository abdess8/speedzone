<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatbotMessageRequest;
use App\Models\Order;
use App\Services\Chatbot\ChatbotService;
use App\Services\Chatbot\ChatDriverException;
use App\Services\Chatbot\RateLimitedException;
use App\Services\Chatbot\Support\OrderInvoicePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ChatbotController extends Controller
{
    public function __construct(private readonly ChatbotService $chatbot) {}

    /**
     * One conversation turn: the user's message in, the assistant's answer plus
     * the actions it performed out.
     */
    public function message(ChatbotMessageRequest $request): JsonResponse
    {
        if (! $this->chatbot->isEnabled()) {
            return response()->json([
                'message' => __('chatbot.errors.disabled'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $reply = $this->chatbot->handle(
                $request->user(),
                $request->message(),
                $request->history(),
            );
        } catch (RateLimitedException $e) {
            // Worth telling apart from a real outage: the user only has to wait.
            Log::info('Chatbot: provider quota exhausted', [
                'user_id' => $request->user()->id,
                'retry_after' => $e->retryAfter,
            ]);

            return response()->json([
                'message' => __('chatbot.errors.busy'),
                'retry_after' => $e->retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        } catch (ChatDriverException $e) {
            // The upstream reason is useful in the log and meaningless (or
            // sensitive) to the user, so only a generic failure crosses over.
            Log::warning('Chatbot: assistant unavailable', [
                'user_id' => $request->user()->id,
                'reason' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('chatbot.errors.unavailable'),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json(['data' => $reply]);
    }

    /**
     * Download the single-order statement offered by `generateInvoicePdf`.
     *
     * A separate authenticated request on purpose: the policy runs again here,
     * so the link is only ever usable by someone who could already print the
     * order themselves.
     */
    public function orderInvoice(Request $request, Order $order, OrderInvoicePdfService $pdfService): Response
    {
        $this->authorize('print', $order);

        $pdf = $pdfService->build($order);
        $fileName = $pdfService->fileName($order);

        return $request->boolean('download')
            ? $pdf->download($fileName)
            : $pdf->stream($fileName);
    }
}
