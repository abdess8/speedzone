<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * How many notifications the bell dropdown keeps in memory.
     */
    private const PAGE_SIZE = 30;

    /**
     * Columns the resource actually reads.
     *
     * @var array<int, string>
     */
    private const COLUMNS = ['id', 'data', 'read_at', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->select(self::COLUMNS)
            ->latest()
            ->limit(self::PAGE_SIZE)
            ->get();

        // The unread badge only needs a number, and the list is capped, so it
        // still has to be counted separately.
        $unreadCount = $request->user()->unreadNotifications()->count();

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        /** @var DatabaseNotification|null $record */
        $record = $request->user()
            ->notifications()
            ->select(self::COLUMNS)
            ->whereKey($notification)
            ->first();

        if (! $record) {
            abort(404);
        }

        if ($record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'data' => new NotificationResource($record),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        // A single UPDATE. Laravel's DatabaseNotificationCollection::markAsRead()
        // hydrates every unread row and issues one UPDATE per notification.
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'unread_count' => 0,
        ]);
    }
}
