<?php

namespace App\Http\Controllers;

use App\Enums\BillingFrequency;
use App\Enums\InvoiceStatus;
use App\Http\Requests\GenerateInvoiceRequest;
use App\Http\Requests\MarkInvoicePaidRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Services\BillingService;
use App\Services\InvoiceGeneratorService;
use App\Services\InvoiceQueryService;
use App\Services\PdfInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly InvoiceGeneratorService $generator,
        private readonly InvoiceQueryService $invoiceQuery,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->invoiceQuery->build($request, $request->user())
            ->paginate($this->invoiceQuery->perPage($request))
            ->withQueryString();

        return Inertia::render('invoices/index', [
            'invoices' => InvoiceResource::collection($invoices)->response()->getData(true),
            'filters' => $request->only([
                'invoice_number', 'seller', 'status', 'created_from', 'created_to',
                'sort', 'direction', 'per_page',
            ]),
            'filterOptions' => [
                'statuses' => InvoiceStatus::options(),
                'pageSizes' => [10, 25, 50, 100],
            ],
            'can' => $this->abilities($request),
        ]);
    }

    /**
     * Manual generation form (admin): pick a seller + optional period.
     */
    public function create(Request $request): Response
    {
        $this->authorize('generate', Invoice::class);

        return Inertia::render('invoices/create', [
            'sellers' => $this->sellerOptions(),
        ]);
    }

    /**
     * Live preview of what would be billed, before confirming generation.
     */
    public function preview(GenerateInvoiceRequest $request): JsonResponse
    {
        $this->authorize('generate', Invoice::class);

        $seller = User::query()->findOrFail($request->integer('seller_id'));
        [$start, $end] = $this->period($request);

        return response()->json($this->billing->preview($seller, $start, $end));
    }

    public function store(GenerateInvoiceRequest $request): RedirectResponse
    {
        $this->authorize('generate', Invoice::class);

        $seller = User::query()->findOrFail($request->integer('seller_id'));
        [$start, $end] = $this->period($request);

        // One invoice per store: a seller with three shops gets three documents.
        $invoices = $this->generator->generateForSeller($seller, $start, $end, $request->user());

        if ($invoices->isEmpty()) {
            return back()->with('error', __('invoices.no_billable_orders'));
        }

        if ($invoices->count() > 1) {
            return redirect()
                ->route('invoices.index')
                ->with('success', __('invoices.generated_many', ['count' => $invoices->count()]));
        }

        $invoice = $invoices->first();

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', __('invoices.generated', ['number' => $invoice->invoice_number]));
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'seller.city',
            'createdBy',
            'paidBy',
            'invoiceOrders.order.city',
            'invoiceOrders.order.sector',
            'logs.user',
        ]);

        return Inertia::render('invoices/show', [
            'invoice' => InvoiceResource::make($invoice)->resolve($request),
            'can' => array_merge($this->abilities($request), [
                'pay' => $request->user()->can('pay', $invoice),
                'cancel' => $request->user()->can('cancel', $invoice),
                'delete' => $request->user()->can('delete', $invoice),
                'print' => $request->user()->can('print', $invoice),
            ]),
        ]);
    }

    /**
     * Seller view: orders waiting to be invoiced + next billing schedule.
     */
    public function pending(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $user = $request->user();

        // Admins may inspect a specific seller; sellers see their own.
        $seller = $user;
        if ($user->hasPermission('invoices.read.all') && $request->filled('seller_id')) {
            $seller = User::query()->findOrFail($request->integer('seller_id'));
        }

        $preview = $this->billing->preview($seller);

        $frequency = $seller->billing_frequency instanceof \BackedEnum
            ? $seller->billing_frequency->value
            : $seller->billing_frequency;

        return Inertia::render('invoices/pending', [
            'preview' => $preview,
            'sellerId' => $seller->id,
            'sellers' => $user->hasPermission('invoices.read.all') ? $this->sellerOptions() : [],
            'billing' => [
                'billing_enabled' => (bool) $seller->billing_enabled,
                'billing_frequency' => $frequency,
                'billing_frequency_label' => $seller->billing_frequency instanceof BillingFrequency
                    ? $seller->billing_frequency->label()
                    : null,
                'next_billing_date' => $seller->next_billing_date?->toDateString(),
            ],
            'can' => $this->abilities($request),
        ]);
    }

    public function pay(MarkInvoicePaidRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        $receiptPath = $request->file('payment_receipt')->store('invoices/receipts', 'public');

        $this->generator->markPaid(
            $invoice,
            $request->user(),
            CarbonImmutable::parse($request->input('paid_at')),
            $receiptPath
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', __('invoices.marked_paid'));
    }

    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        $this->generator->markSent($invoice, $request->user());

        return back()->with('success', __('invoices.marked_sent'));
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('cancel', $invoice);

        $this->generator->cancel($invoice, $request->user());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', __('invoices.cancelled'));
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $this->generator->delete($invoice, $request->user());

        return redirect()
            ->route('invoices.index')
            ->with('success', __('invoices.deleted'));
    }

    public function pdf(Request $request, Invoice $invoice, PdfInvoiceService $pdfService): HttpResponse
    {
        $this->authorize('print', $invoice);

        $fileName = $pdfService->fileName($invoice);
        $path = $pdfService->storedPath($invoice);

        if (! $path) {
            // Regenerate on demand if the stored file is missing.
            $pdf = $pdfService->build($invoice);

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
     * Normalise the optional billing period from the request.
     *
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
    private function sellerOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::SELLER))
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
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        return [
            'generate' => $user->hasPermission('invoices.generate'),
            'read_all' => $user->hasPermission('invoices.read.all'),
            'pay' => $user->hasPermission('invoices.pay'),
            'cancel' => $user->hasPermission('invoices.cancel'),
            'delete' => $user->hasPermission('invoices.delete'),
            'print' => $user->hasPermission('invoices.print'),
        ];
    }
}
