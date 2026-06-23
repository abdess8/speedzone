<?php

namespace App\Http\Controllers;

use App\Enums\DriverTransactionType;
use App\Http\Resources\DriverInvoiceResource;
use App\Models\DriverInvoice;
use App\Models\DriverTransaction;
use App\Models\User;
use App\Services\DriverBillingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverFinanceController extends Controller
{
    public function __construct(private readonly DriverBillingService $billing) {}

    /**
     * Driver finance dashboard: earnings, paid orders and invoices.
     */
    public function dashboard(Request $request): Response
    {
        $this->authorize('viewAny', DriverInvoice::class);

        $user = $request->user();

        // Admins may inspect a specific driver; drivers see their own data.
        $driver = $user;
        if ($user->hasPermission('driver_invoices.read.all') && $request->filled('driver_id')) {
            $driver = User::query()->findOrFail($request->integer('driver_id'));
        }

        $transactions = DriverTransaction::query()
            ->forDriver($driver->id)
            ->where('transaction_type', DriverTransactionType::DELIVERY_PAYMENT->value)
            ->with(['order.city', 'sector', 'order.sector'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $invoices = DriverInvoice::query()
            ->forDriver($driver->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return Inertia::render('driver-finance/dashboard', [
            'stats' => $this->billing->dashboardStats($driver),
            'driverId' => $driver->id,
            'drivers' => $user->hasPermission('driver_invoices.read.all') ? $this->driverOptions() : [],
            'transactions' => $transactions->map(function (DriverTransaction $tx) {
                $status = $tx->status instanceof \App\Enums\DriverTransactionStatus
                    ? $tx->status
                    : \App\Enums\DriverTransactionStatus::from($tx->status);

                return [
                    'id' => $tx->id,
                    'order_id' => $tx->order_id,
                    'tracking_number' => $tx->order?->tracking_number,
                    'customer_full_name' => $tx->order?->customer_full_name,
                    'city' => $tx->order?->city?->name,
                    'sector' => $tx->sector?->name ?? $tx->order?->sector?->name,
                    'amount' => (float) $tx->amount,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_color' => $status->color(),
                    'created_at' => $tx->created_at?->toIso8601String(),
                ];
            })->all(),
            'invoices' => DriverInvoiceResource::collection($invoices)->resolve($request),
            'can' => [
                'read_all' => $user->hasPermission('driver_invoices.read.all'),
                'print' => $user->hasPermission('driver_invoices.print'),
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function driverOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', \App\Models\Role::DRIVER))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ])
            ->all();
    }
}
