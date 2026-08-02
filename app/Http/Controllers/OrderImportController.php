<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\ImportOrdersRequest;
use App\Models\City;
use App\Models\Order;
use App\Models\Sector;
use App\Services\OrderImportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bulk creation of orders from a CSV/Excel file.
 *
 * Parsing, column mapping and row repair all happen in the browser; this
 * controller only serves the reference data the wizard resolves cities and
 * sectors against, and re-validates the finished batch before writing it.
 */
class OrderImportController extends Controller
{
    public function __construct(
        private readonly OrderImportService $importService,
    ) {}

    public function create(): Response
    {
        $this->authorize('create', Order::class);

        return Inertia::render('orders/import', [
            'cities' => $this->cityOptions(),
            // The whole sector table is shipped up front rather than fetched per
            // row: the review table needs a picker on every line, and a file of
            // three hundred orders would otherwise open three hundred requests.
            'sectors' => $this->sectorOptions(),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    public function store(ImportOrdersRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $orders = $this->importService->import($request->rows(), $request->user());

        return redirect()
            ->route('orders.index')
            ->with('success', __('orders.import.flash.created', ['count' => $orders->count()]));
    }

    /**
     * @return array<int, array{id: int, name: string, code: ?string, region: ?string}>
     */
    private function cityOptions(): array
    {
        return City::options();
    }

    /**
     * @return array<int, array{id: int, city_id: int, name: string, delivery_price: float}>
     */
    private function sectorOptions(): array
    {
        return Sector::query()
            ->active()
            ->whereHas('city', fn ($query) => $query->active())
            ->orderBy('name')
            ->get(['id', 'city_id', 'name', 'delivery_price'])
            ->map(fn (Sector $sector) => [
                'id' => $sector->id,
                'city_id' => $sector->city_id,
                'name' => $sector->name,
                'delivery_price' => (float) $sector->delivery_price,
            ])
            ->all();
    }
}
