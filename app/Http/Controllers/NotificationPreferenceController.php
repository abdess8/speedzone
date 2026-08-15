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
        // Only the topics the user's role entitles him to are accepted: an
        // unlisted key is not a preference he owns.
        $rules = [];

        foreach ($this->preferences->editableKeys($request->user()) as $key) {
            $rules[$key] = ['sometimes', 'boolean'];
        }

        $preference = $this->preferences->update($request->user(), $request->validate($rules));

        return response()->json([
            'data' => $preference->only($this->preferences->editableKeys($request->user())),
        ]);
    }
}
