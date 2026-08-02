<?php

namespace App\Http\Controllers;

use App\Enums\BillingFrequency;
use App\Enums\DriverInvoiceStatus;
use App\Http\Requests\GenerateDriverInvoiceRequest;
use App\Http\Requests\MarkDriverInvoicePaidRequest;
use App\Http\Resources\DriverInvoiceResource;
use App\Models\DriverInvoice;
use App\Models\Role;
use App\Models\User;
use App\Policies\DriverInvoicePolicy;
use App\Services\DriverBillingService;
use App\Services\DriverInvoiceGeneratorService;
use App\Services\DriverInvoiceQueryService;
use App\Services\DriverPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DriverInvoiceController extends Controller
{
    public function __construct(
        private readonly DriverBillingService $billing,
        private readonly DriverInvoiceGeneratorService $generator,
        private readonly DriverInvoiceQueryService $invoiceQuery,
        private readonly DriverInvoicePolicy $invoicePolicy,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DriverInvoice::class);

        $invoices = $this->invoiceQuery->build($request, $request->user())
            ->paginate($this->invoiceQuery->perPage($request))
            ->withQueryString();

        return Inertia::render('driver-invoices/index', [
            'invoices' => DriverInvoiceResource::collection($invoices)->response()->getData(true),
            'filters' => $request->only([
                'invoice_number', 'driver', 'status', 'created_from', 'created_to',
                'sort', 'direction', 'per_page',
            ]),
            'filterOptions' => [
                'statuses' => DriverInvoiceStatus::options(),
                'pageSizes' => [10, 25, 50, 100],
            ],
            'can' => $this->abilities($request),
        ]);
    }

    /**
     * Admin page to settle driver invoices (mark them as paid).
     */
    public function payments(Request $request): Response
    {
        $this->authorize('viewAny', DriverInvoice::class);
        abort_unless($request->user()->hasPermission('driver_invoices.pay'), 403);

        $invoices = DriverInvoice::query()
            ->with('driver')
            ->where('status', DriverInvoiceStatus::GENERATED->value)
            ->orderByDesc('generated_at')
            ->paginate($this->invoiceQuery->perPage($request))
            ->withQueryString();

        return Inertia::render('driver-invoices/payments', [
            'invoices' => DriverInvoiceResource::collection($invoices)->response()->getData(true),
            'can' => $this->abilities($request),
        ]);
    }

    /**
     * Manual generation form (admin): pick a driver + optional period.
     */
    public function create(Request $request): Response
    {
        $this->authorize('generate', DriverInvoice::class);

        return Inertia::render('driver-invoices/create', [
            'drivers' => $this->driverOptions(),
        ]);
    }

    /**
     * Live preview of what would be billed, before confirming generation.
     */
    public function preview(GenerateDriverInvoiceRequest $request): JsonResponse
    {
        $this->authorize('generate', DriverInvoice::class);

        $driver = User::query()->findOrFail($request->integer('driver_id'));
        [$start, $end] = $this->period($request);

        return response()->json($this->billing->preview($driver, $start, $end));
    }

    public function store(GenerateDriverInvoiceRequest $request): RedirectResponse
    {
        $this->authorize('generate', DriverInvoice::class);

        $driver = User::query()->findOrFail($request->integer('driver_id'));
        [$start, $end] = $this->period($request);

        $invoice = $this->generator->generate($driver, $start, $end, $request->user());

        if (! $invoice) {
            return back()->with('error', __('driver_invoices.no_billable_transactions'));
        }

        return redirect()
            ->route('driver-invoices.show', $invoice)
            ->with('success', __('driver_invoices.generated', ['number' => $invoice->invoice_number]));
    }

    public function show(Request $request, DriverInvoice $driverInvoice): Response
    {
        $this->authorize('view', $driverInvoice);

        $driverInvoice->load([
            'driver.city',
            'createdBy',
            'paidBy',
            'invoiceTransactions.driverTransaction.order.city',
            'invoiceTransactions.driverTransaction.order.sector',
            'invoiceTransactions.driverTransaction.sector',
            'logs.user',
        ]);

        return Inertia::render('driver-invoices/show', [
            'invoice' => DriverInvoiceResource::make($driverInvoice)->resolve($request),
            'can' => array_merge($this->abilities($request), $this->invoiceActionAbilities($request->user(), $driverInvoice)),
        ]);
    }

    /**
     * Admin/driver view: transactions waiting to be invoiced + billing schedule.
     */
    public function pending(Request $request): Response
    {
        $this->authorize('viewAny', DriverInvoice::class);

        $user = $request->user();

        $driver = $user;
        if ($user->hasPermission('driver_invoices.read.all') && $request->filled('driver_id')) {
            $driver = User::query()->findOrFail($request->integer('driver_id'));
        }

        $preview = $this->billing->preview($driver);

        $frequency = $driver->billing_frequency instanceof \BackedEnum
            ? $driver->billing_frequency->value
            : $driver->billing_frequency;

        return Inertia::render('driver-invoices/pending', [
            'preview' => $preview,
            'driverId' => $driver->id,
            'drivers' => $user->hasPermission('driver_invoices.read.all') ? $this->driverOptions() : [],
            'billing' => [
                'billing_enabled' => (bool) $driver->billing_enabled,
                'billing_frequency' => $frequency,
                'billing_frequency_label' => $driver->billing_frequency instanceof BillingFrequency
                    ? $driver->billing_frequency->label()
                    : null,
                'next_billing_date' => $driver->next_billing_date?->toDateString(),
            ],
            'can' => $this->abilities($request),
        ]);
    }

    public function pay(MarkDriverInvoicePaidRequest $request, DriverInvoice $driverInvoice): RedirectResponse
    {
        if ($denied = $this->denyUnlessInvoiceAction('pay', $request->user(), $driverInvoice)) {
            return $denied;
        }

        $receiptPath = $request->file('payment_receipt')->store('driver-invoices/receipts', 'public');

        $this->generator->markPaid(
            $driverInvoice,
            $request->user(),
            CarbonImmutable::parse($request->input('paid_at')),
            $receiptPath
        );

        return redirect()
            ->route('driver-invoices.show', $driverInvoice)
            ->with('success', __('driver_invoices.marked_paid'));
    }

    public function cancel(Request $request, DriverInvoice $driverInvoice): RedirectResponse
    {
        if ($denied = $this->denyUnlessInvoiceAction('cancel', $request->user(), $driverInvoice)) {
            return $denied;
        }

        $this->generator->cancel($driverInvoice, $request->user());

        return redirect()
            ->route('driver-invoices.show', $driverInvoice)
            ->with('success', __('driver_invoices.cancelled'));
    }

    public function destroy(Request $request, DriverInvoice $driverInvoice): RedirectResponse
    {
        if ($denied = $this->denyUnlessInvoiceAction('delete', $request->user(), $driverInvoice)) {
            return $denied;
        }

        $this->generator->delete($driverInvoice, $request->user());

        return redirect()
            ->route('driver-invoices.index')
            ->with('success', __('driver_invoices.deleted'));
    }

    public function pdf(Request $request, DriverInvoice $driverInvoice, DriverPdfService $pdfService): HttpResponse
    {
        $this->authorize('print', $driverInvoice);

        $fileName = $pdfService->fileName($driverInvoice);
        $path = $pdfService->storedPath($driverInvoice);

        if (! $path) {
            $pdf = $pdfService->build($driverInvoice);

            return $request->boolean('download')
                ? $pdf->download($fileName)
                : $pdf->stream($fileName);
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
        ]);
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $start = $request->filled('period_start') ? CarbonImmutable::parse($request->input('period_start')) : null;
        $end = $request->filled('period_end') ? CarbonImmutable::parse($request->input('period_end')) : null;

        return [$start, $end];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function driverOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::DRIVER))
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

    /**
     * Per-invoice action flags that respect invoice status (not bypassed by Gate::before).
     *
     * @return array{pay: bool, cancel: bool, delete: bool, print: bool}
     */
    private function invoiceActionAbilities(User $user, DriverInvoice $invoice): array
    {
        return [
            'pay' => $this->invoicePolicy->allows('pay', $user, $invoice),
            'cancel' => $this->invoicePolicy->allows('cancel', $user, $invoice),
            'delete' => $this->invoicePolicy->allows('delete', $user, $invoice),
            'print' => $this->invoicePolicy->allows('print', $user, $invoice),
        ];
    }

    private function denyUnlessInvoiceAction(string $ability, User $user, DriverInvoice $invoice): ?RedirectResponse
    {
        if ($this->invoicePolicy->allows($ability, $user, $invoice)) {
            return null;
        }

        $status = $invoice->status instanceof DriverInvoiceStatus
            ? $invoice->status
            : DriverInvoiceStatus::from($invoice->status);

        return back()->with('error', __('driver_invoices.errors.invalid_status', [
            'number' => $invoice->invoice_number,
            'status' => $status->label(),
        ]));
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'generate' => $user->hasPermission('driver_invoices.generate'),
            'read_all' => $user->hasPermission('driver_invoices.read.all'),
            'pay' => $user->hasPermission('driver_invoices.pay'),
            'cancel' => $user->hasPermission('driver_invoices.cancel'),
            'delete' => $user->hasPermission('driver_invoices.delete'),
            'print' => $user->hasPermission('driver_invoices.print'),
        ];
    }
}
