<?php

namespace App\Search\Providers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Search\SearchHit;
use App\Services\InvoiceQueryService;

class InvoiceSearchProvider extends AbstractSearchProvider
{
    public function __construct(private readonly InvoiceQueryService $invoices) {}

    public function key(): string
    {
        return 'invoices';
    }

    public function label(): string
    {
        return __('search.objects.invoices');
    }

    public function icon(): string
    {
        return 'ri-bill-line';
    }

    public function availableTo(User $user): bool
    {
        return $this->canAny($user, ['invoices.read.all', 'invoices.read.own']);
    }

    public function search(User $user, string $term, int $limit): array
    {
        $invoices = $this->invoices
            ->build($this->unfiltered(), $user)
            ->where('invoice_number', 'like', $this->like($term))
            ->limit($limit)
            ->get();

        return $invoices->map(function (Invoice $invoice): SearchHit {
            $status = $invoice->status instanceof InvoiceStatus
                ? $invoice->status
                : InvoiceStatus::tryFrom((string) $invoice->status);

            $period = $invoice->period_start && $invoice->period_end
                ? $this->date($invoice->period_start).' – '.$this->date($invoice->period_end)
                : null;

            return new SearchHit(
                id: $invoice->id,
                title: $invoice->invoice_number,
                subtitle: $invoice->seller?->full_name,
                url: route('invoices.show', $invoice),
                preview: [
                    __('search.fields.seller') => $invoice->seller?->full_name,
                    __('search.fields.period') => $period,
                    __('search.fields.orders_count') => (string) $invoice->total_orders_count,
                    __('search.fields.net_amount') => $this->money($invoice->net_amount),
                    __('search.fields.created_at') => $this->date($invoice->created_at),
                ],
                badge: $status?->label(),
                badgeColor: $status?->color(),
            );
        })->all();
    }
}
