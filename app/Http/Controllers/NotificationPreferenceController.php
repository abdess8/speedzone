<?php

namespace App\Http\Controllers;

use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationPreferenceService $preferences) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->preferences->toArray($request->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'invoice_generated' => ['sometimes', 'boolean'],
            'ticket_created' => ['sometimes', 'boolean'],
            'ticket_message' => ['sometimes', 'boolean'],
            'ticket_closed' => ['sometimes', 'boolean'],
            'return_requested' => ['sometimes', 'boolean'],
            'system_notifications' => ['sometimes', 'boolean'],
        ]);

        $preference = $this->preferences->update($request->user(), $validated);

        return response()->json([
            'data' => $preference->only(array_keys($this->preferences->defaults())),
        ]);
    }
}
