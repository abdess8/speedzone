<?php /** @var \App\Models\Invoice $invoice */ ?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1c2333; font-size: 11px; }
        .wrap { padding: 28px 32px; }
        .row { width: 100%; }
        .clearfix::after { content: ""; display: table; clear: both; }
        .col-left { float: left; width: 55%; }
        .col-right { float: right; width: 40%; text-align: right; }
        .logo { max-height: 56px; max-width: 200px; }
        .brand { font-size: 18px; font-weight: bold; color: #0d6efd; }
        .doc-title { font-size: 22px; font-weight: bold; letter-spacing: 1px; color: #1c2333; }
        .muted { color: #6c757d; }
        .mt-2 { margin-top: 8px; } .mt-3 { margin-top: 14px; } .mt-4 { margin-top: 22px; }
        .panel { border: 1px solid #e5e8ef; border-radius: 6px; padding: 12px 14px; }
        .panel h4 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #6c757d; letter-spacing: .5px; }
        .panel .name { font-weight: bold; font-size: 13px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { background: #f3f5f9; text-align: left; padding: 7px 8px; font-size: 10px; text-transform: uppercase; color: #56607a; border-bottom: 1px solid #e5e8ef; }
        table.items td { padding: 7px 8px; border-bottom: 1px solid #eef1f6; }
        table.items td.num, table.items th.num { text-align: right; }
        .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-delivered { background: #d1f5e0; color: #0a7d3f; }
        .badge-returned { background: #e2e5ec; color: #3a4256; }
        .totals { width: 46%; float: right; margin-top: 12px; }
        .totals td { padding: 5px 8px; }
        .totals .label { color: #56607a; }
        .totals .value { text-align: right; font-weight: bold; }
        .totals .net td { border-top: 2px solid #0d6efd; font-size: 14px; color: #0d6efd; padding-top: 8px; }
        .status-chip { display:inline-block; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; background:#e7f1ff; color:#0d6efd; }
        .footer { position: fixed; bottom: 18px; left: 32px; right: 32px; color: #98a2b3; font-size: 9px; border-top: 1px solid #e5e8ef; padding-top: 6px; }
        /* Arabic values are emitted in visual order, so they only need to hang
           right. Table cells additionally refuse to wrap: a break decided by the
           engine would put the end of the value on the first line. */
        .rtl { text-align: right; }
        table.items td.rtl, table.items td.date { white-space: nowrap; }
    </style>
</head>
<body>
@php
    $money = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $status = $invoice->status instanceof \App\Enums\InvoiceStatus ? $invoice->status : \App\Enums\InvoiceStatus::from($invoice->status);
@endphp
<div class="wrap">
    <div class="row clearfix">
        <div class="col-left">
            @if($logo)
                <img src="{{ $logo }}" class="logo" alt="logo">
            @else
                <div class="brand">@bidi($companyName)</div>
            @endif
            <div class="muted mt-2">@bidi($companyName)</div>
        </div>
        <div class="col-right">
            <div class="doc-title">{{ __('invoices.pdf.title') }}</div>
            <div class="mt-2"><strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="muted">{{ __('invoices.pdf.generated_on') }}: {{ optional($invoice->generated_at)->format('Y-m-d H:i') }}</div>
            <div class="mt-2"><span class="status-chip">{{ $status->label() }}</span></div>
        </div>
    </div>

    <div class="row clearfix mt-4">
        <div class="col-left">
            <div class="panel">
                <h4>{{ __('invoices.pdf.billed_to') }}</h4>
                <div class="name @bidiclass($seller->full_name)">@bidi($seller->full_name)</div>
                @if($seller->ice_number)<div class="muted">ICE: {{ $seller->ice_number }}</div>@endif
                @if($seller->phone_number)<div class="muted">{{ $seller->phone_number }}</div>@endif
                @if($seller->address)<div class="muted @bidiclass($seller->address)">@bidilines($seller->address, 46)</div>@endif
            </div>
        </div>
        <div class="col-right" style="text-align:left;">
            <div class="panel">
                <h4>{{ __('invoices.pdf.payment_details') }}</h4>
                @if($seller->bank_name)<div><span class="muted">{{ __('invoices.pdf.bank') }}:</span> @bidi($seller->bank_name)</div>@endif
                @if($seller->rib)<div><span class="muted">RIB:</span> {{ $seller->rib }}</div>@endif
                @if($invoice->period_start || $invoice->period_end)
                    <div class="mt-2"><span class="muted">{{ __('invoices.pdf.period') }}:</span>
                        {{ optional($invoice->period_start)->format('Y-m-d') ?? '—' }} → {{ optional($invoice->period_end)->format('Y-m-d') ?? '—' }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <table class="items mt-4">
        <thead>
        <tr>
            <th>#</th>
            <th>{{ __('invoices.pdf.order') }}</th>
            <th>{{ __('invoices.pdf.customer') }}</th>
            <th>{{ __('invoices.pdf.city') }}</th>
            <th>{{ __('invoices.pdf.status') }}</th>
            <th>{{ __('invoices.pdf.completed_on') }}</th>
            <th class="num">{{ __('invoices.pdf.order_amount') }}</th>
            <th class="num">{{ __('invoices.pdf.delivery') }}</th>
            <th class="num">{{ __('invoices.pdf.return') }}</th>
            <th class="num">{{ __('invoices.pdf.total') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lines as $i => $line)
            @php
                $order = $line->order;
                $isReturned = $line->order_status_at_invoice === \App\Enums\OrderStatus::RETURNED->value;
                $orderStatus = \App\Enums\OrderStatus::tryFrom((string) $line->order_status_at_invoice);
                $completedAt = $line->order_completed_at ?? $order?->completedAt();
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $order?->tracking_number ?? '#'.$line->order_id }}</td>
                <td class="@bidiclass($order?->customer_full_name)">@bidilines($order?->customer_full_name ?? '—', 18)</td>
                <td class="@bidiclass($order?->city?->name)">@bidilines($order?->city?->name ?? '—', 14)</td>
                <td>
                    <span class="badge {{ $isReturned ? 'badge-returned' : 'badge-delivered' }}">
                        {{ $orderStatus?->label() ?? $line->order_status_at_invoice }}
                    </span>
                </td>
                <td class="date">{{ $completedAt?->format('Y-m-d') ?? '—' }}</td>
                <td class="num">{{ $money($line->order_amount) }}</td>
                <td class="num">{{ $money($line->delivery_fee) }}</td>
                <td class="num">{{ $money($line->return_fee) }}</td>
                <td class="num">{{ $money($line->final_amount) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="row clearfix">
        <table class="totals">
            <tr>
                <td class="label">{{ __('invoices.pdf.total_orders') }}</td>
                <td class="value">{{ $invoice->total_orders_count }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('invoices.pdf.total_delivered') }}</td>
                <td class="value">{{ $money($invoice->delivered_amount) }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('invoices.pdf.total_returned') }}</td>
                <td class="value">{{ $money($invoice->returned_amount) }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('invoices.pdf.delivery_fees') }}</td>
                <td class="value">- {{ $money($invoice->delivery_fees_total) }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('invoices.pdf.return_fees') }}</td>
                <td class="value">- {{ $money($invoice->return_fees_total) }}</td>
            </tr>
            <tr class="net">
                <td class="label">{{ __('invoices.pdf.net_payable') }}</td>
                <td class="value">{{ $money($invoice->net_amount) }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="footer clearfix">
    <span style="float:left;">{{ $invoice->invoice_number }}</span>
    <span style="float:right;">{{ __('invoices.pdf.generated_on') }}: {{ optional($invoice->generated_at)->format('Y-m-d H:i') }}</span>
</div>
</body>
</html>
