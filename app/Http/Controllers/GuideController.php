<?php

namespace App\Http\Controllers;

use App\Models\UserGuideProgress;
use App\Support\Guides\GuideCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Help Center and the progress it remembers.
 *
 * `index` is an ordinary Inertia page. The two write endpoints answer JSON
 * instead of redirecting, because they are called from inside a running tour:
 * an Inertia redirect would remount the page under the reader's feet and lose
 * the very step he is standing on.
 */
class GuideController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('guides/index', [
            'guides' => GuideCatalog::forUser($user),
            'progress' => $this->progressFor($request),
        ]);
    }

    /**
     * Record that a guide was started, advanced or finished.
     */
    public function store(Request $request, string $guide): JsonResponse
    {
        abort_unless(GuideCatalog::has($guide), 404);
        $this->authorizeGuide($request, $guide);

        $data = $request->validate([
            'step' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['started', 'in_progress', 'completed'])],
        ]);

        $progress = UserGuideProgress::firstOrNew([
            'user_id' => $request->user()->id,
            'guide_key' => $guide,
        ]);

        $progress->started_at ??= now();
        $progress->last_step_index = $data['step'];

        if ($data['status'] === 'completed') {
            $progress->completed_at = now();
            $progress->completed_count++;
            // A finished guide reopens at the welcome step, not at the end.
            $progress->last_step_index = 0;
        }

        $progress->save();

        return response()->json(['data' => $this->serialize($progress)]);
    }

    /**
     * Forget a guide, so the Help Center offers it as new again.
     */
    public function destroy(Request $request, string $guide): JsonResponse
    {
        abort_unless(GuideCatalog::has($guide), 404);
        $this->authorizeGuide($request, $guide);

        UserGuideProgress::where('user_id', $request->user()->id)
            ->where('guide_key', $guide)
            ->delete();

        return response()->json(['data' => null]);
    }

    /**
     * Refuse to track a guide the reader would not be offered.
     *
     * Cheap, but it keeps the table free of rows nobody can act on and stops a
     * hand-crafted request from probing which guides exist for other roles.
     */
    private function authorizeGuide(Request $request, string $guide): void
    {
        $allowed = collect(GuideCatalog::forUser($request->user()))->pluck('key');

        abort_unless($allowed->contains($guide), 403);
    }

    /**
     * Progress keyed by guide, shaped for the client store.
     *
     * @return array<string, array<string, mixed>>
     */
    private function progressFor(Request $request): array
    {
        return UserGuideProgress::where('user_id', $request->user()->id)
            ->whereIn('guide_key', GuideCatalog::keys())
            ->get()
            ->mapWithKeys(fn (UserGuideProgress $row) => [$row->guide_key => $this->serialize($row)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(UserGuideProgress $progress): array
    {
        return [
            'guide_key' => $progress->guide_key,
            'completed' => $progress->isCompleted(),
            'completed_count' => $progress->completed_count,
            'completed_at' => $progress->completed_at?->toIso8601String(),
            'last_step_index' => $progress->last_step_index,
        ];
    }
}
