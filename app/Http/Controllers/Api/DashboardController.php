<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use App\Support\DashboardDateRange;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(DashboardRequest $request): DashboardResource|JsonResponse
    {
        $user = $request->user();

        $locale = $user?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale', 'fr');

        if (in_array($locale, ['fr', 'en'], true)) {
            app()->setLocale($locale);
        }

        try {
            $range = DashboardDateRange::fromRequest($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $data = $this->dashboard->get($request->user(), $range);

        return DashboardResource::make($data);
    }
}
