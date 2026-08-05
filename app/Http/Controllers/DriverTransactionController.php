<?php

namespace App\Http\Controllers;

use App\Enums\DriverTransactionType;
use App\Http\Requests\StoreDriverTransactionRequest;
use App\Models\DriverInvoice;
use App\Models\DriverTransaction;
use App\Models\Role;
use App\Models\User;
use App\Services\DriverPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Manual entries on a driver's ledger: bonuses, penalties and adjustments.
 *
 * Delivery payments are not handled here — they are generated automatically
 * when an order is delivered and must never be typed in by hand.
 */
class DriverTransactionController extends Controller
{
    public function __construct(private readonly DriverPaymentService $payments) {}

    public function store(StoreDriverTransactionRequest $request): RedirectResponse
    {
        $this->authorize('adjust', DriverInvoice::class);

        $driver = $this->findDriver($request->integer('driver_id'));
        $type = DriverTransactionType::from($request->string('transaction_type')->toString());

        $this->payments->recordManualTransaction(
            $driver,
            $type,
            (float) $request->input('amount'),
            $request->input('note'),
            $request->user(),
        );

        return back()->with('success', __('driver_invoices.transactions.created', [
            'type' => $type->label(),
            'driver' => $driver->full_name,
        ]));
    }

    public function destroy(Request $request, DriverTransaction $driverTransaction): RedirectResponse
    {
        $this->authorize('adjust', DriverInvoice::class);

        try {
            $this->payments->deleteManualTransaction($driverTransaction, $request->user());
        } catch (InvalidArgumentException) {
            return back()->with('error', __('driver_invoices.transactions.not_deletable'));
        }

        return back()->with('success', __('driver_invoices.transactions.deleted'));
    }

    /**
     * Crediting a non-driver account would silently create money nobody can
     * settle, so the role is enforced rather than trusted from the payload.
     */
    private function findDriver(int $driverId): User
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', Role::DRIVER))
            ->findOrFail($driverId);
    }
}
