<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Middleware\ResolveActiveStore;
use App\Models\Sector;
use App\Services\OrderQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiIntegrationController extends Controller
{
    /**
     * Requests per minute allowed on the API, mirroring the `api` rate limiter
     * declared in RouteServiceProvider.
     */
    private const RATE_LIMIT = 60;

    public function index(Request $request): Response
    {
        return Inertia::render('settings/api-integrations', [
            'apiBaseUrl' => $this->baseUrl($request),
            'storeHeader' => ResolveActiveStore::HEADER,
            'rateLimit' => self::RATE_LIMIT,
            'tokensUrl' => route('api-tokens.index'),
            'stores' => fn () => $this->stores($request),
            'orderStatuses' => fn () => OrderStatus::options(),
            'statusGroups' => fn () => $this->statusGroups(),
            'examples' => fn () => $this->examples(),
        ]);
    }

    /**
     * A city and one of its sectors that really exist.
     *
     * The Postman collection seeds its `cityId` / `sectorId` variables with
     * these, so a freshly imported collection can create an order without the
     * reader first having to go hunting for valid identifiers.
     *
     * @return array{city_id: int|null, city_name: string|null, sector_id: int|null, sector_name: string|null}
     */
    private function examples(): array
    {
        $sector = Sector::query()
            ->where('sectors.is_active', true)
            ->whereHas('city', fn ($query) => $query->where('is_active', true))
            ->with('city:id,name')
            ->orderBy('sectors.city_id')
            ->orderBy('sectors.id')
            ->first(['sectors.id', 'sectors.city_id', 'sectors.name']);

        return [
            'city_id' => $sector?->city_id,
            'city_name' => $sector?->city?->name,
            'sector_id' => $sector?->id,
            'sector_name' => $sector?->name,
        ];
    }

    /**
     * The host every sample on the page, and the Postman collection, points at.
     *
     * `php artisan serve` falls back to whatever port is free, so a local
     * APP_URL of `http://localhost` sends readers to port 80 and every request
     * they copy fails to connect. Locally we therefore quote the host the
     * reader actually reached us on, which is by definition reachable.
     * Deployed, the configured URL stays authoritative: it is the canonical
     * address, and it cannot be downgraded to http by an untrusted proxy.
     */
    private function baseUrl(Request $request): string
    {
        if (app()->environment('local')) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Stores the reader may target through the X-Store-Id header.
     *
     * @return array<int, array{id: int, name: string, is_default: bool}>
     */
    private function stores(Request $request): array
    {
        $user = $request->user();

        if (! $user?->belongsToStoreAccount()) {
            return [];
        }

        return $user->stores()
            ->where('stores.is_active', true)
            ->orderByDesc('stores.is_default')
            ->orderBy('stores.name')
            ->get(['stores.id', 'stores.name', 'stores.is_default'])
            ->map(fn ($store) => [
                'id' => (int) $store->id,
                'name' => (string) $store->name,
                'is_default' => (bool) $store->is_default,
            ])
            ->all();
    }

    /**
     * The `status_group` shortcuts, resolved to the statuses they cover.
     *
     * @return array<int, array{value: string, statuses: array<int, string>}>
     */
    private function statusGroups(): array
    {
        return collect(array_keys(OrderQueryService::STATUS_GROUPS))
            ->map(fn (string $group) => [
                'value' => $group,
                'statuses' => OrderQueryService::statusGroup($group),
            ])
            ->all();
    }
}
