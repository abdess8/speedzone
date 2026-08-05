<?php /** @var \App\Models\DriverInvoice $invoice */ ?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        /* The page margins, not a padding on the content, are what reserve room
           on *every* sheet: a padded wrapper only indents the first and last
           page, so a long invoice used to run its rows straight over the fixed
           footer. The bottom margin is the footer's height plus air. */
        @page { margin: 18mm 11mm 20mm 11mm; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1c2333; font-size: 11px; }
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
        .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: bold; background: #d1f5e0; color: #0a7d3f; }
        /* Pushed right with a margin rather than a float: dompdf sends a
           floated table to the next page whenever the float lands below the
           line it was opened on, which parked the totals of a short invoice
           alone on a second sheet. */
        .totals { width: 46%; margin: 12px 0 0 54%; }
        .totals td { padding: 5px 8px; }
        .totals .label { color: #56607a; }
        .totals .value { text-align: right; font-weight: bold; }
        .totals .net td { border-top: 2px solid #0d6efd; font-size: 14px; color: #0d6efd; padding-top: 8px; }
        .status-chip { display:inline-block; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; background:#e7f1ff; color:#0d6efd; }
        /* Negative offsets park these two inside the page margins reserved
           above, which is how dompdf produces a running header and footer:
           fixed blocks are repeated on every sheet. */
        .running-head { position: fixed; top: -12mm; left: 0; right: 0; height: 9mm; color: #98a2b3; font-size: 9px; border-bottom: 1px solid #eef1f6; padding-bottom: 4px; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; height: 12mm; color: #98a2b3; font-size: 9px; border-top: 1px solid #e5e8ef; padding-top: 6px; }
        /* Arabic values are emitted in visual order, so they only need to hang
           right. Table cells additionally refuse to wrap: a break decided by the
           engine would put the end of the value on the first line. */
        .rtl { text-align: right; }
        table.items td.rtl { white-space: nowrap; }
        /* A line cut in half across two sheets is unreadable, and a total
           separated from the rows it comes from is worse. `thead` is repeated
           by dompdf on its own, so page two still names its columns. */
        table.items tr { page-break-inside: avoid; }
        table.totals { page-break-inside: avoid; }
    </style>
</head>
<body>
@php
    $money = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $status = $invoice->status instanceof \App\Enums\DriverInvoiceStatus ? $invoice->status : \App\Enums\DriverInvoiceStatus::from($invoice->status);
@endphp

{{-- Repeated on every sheet: from page two on, the header below is gone and
     these are the only marks that say which invoice the rows belong to. --}}
<div class="running-head clearfix">
    <span style="float:left;">{{ $invoice->invoice_number }}</span>
    <span style="float:right;" class="@bidiclass($driver->full_name)">@bidi($driver->full_name)</span>
</div>

<div>
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
            <div class="doc-title">{{ __('driver_invoices.pdf.title') }}</div>
            <div class="mt-2"><strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="muted">{{ __('driver_invoices.pdf.generated_on') }}: {{ optional($invoice->generated_at)->format('Y-m-d H:i') }}</div>
            <div class="mt-2"><span class="status-chip">{{ $status->label() }}</span></div>
        </div>
    </div>

    <div class="row clearfix mt-4">
        <div class="col-left">
            <div class="panel">
                <h4>{{ __('driver_invoices.pdf.driver') }}</h4>
                <div class="name @bidiclass($driver->full_name)">@bidi($driver->full_name)</div>
                @if($driver->phone_number)<div class="muted">{{ $driver->phone_number }}</div>@endif
                @if($driver->cin)<div class="muted">CIN: {{ $driver->cin }}</div>@endif
                @if($driver->address)<div class="muted @bidiclass($driver->address)">@bidilines($driver->address, 46)</div>@endif
            </div>
        </div>
        <div class="col-right" style="text-align:left;">
            <div class="panel">
                <h4>{{ __('driver_invoices.pdf.payment_details') }}</h4>
                @if($driver->bank_name)<div><span class="muted">{{ __('driver_invoices.pdf.bank') }}:</span> @bidi($driver->bank_name)</div>@endif
                @if($driver->rib)<div><span class="muted">RIB:</span> {{ $driver->rib }}</div>@endif
                @if($invoice->period_start || $invoice->period_end)
                    <div class="mt-2"><span class="muted">{{ __('driver_invoices.pdf.period') }}:</span>
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
            <th>{{ __('driver_invoices.pdf.order') }}</th>
            <th>{{ __('driver_invoices.pdf.customer') }}</th>
            <th>{{ __('driver_invoices.pdf.city') }}</th>
            <th>{{ __('driver_invoices.pdf.sector') }}</th>
            <th>{{ __('driver_invoices.pdf.type') }}</th>
            <th class="num">{{ __('driver_invoices.pdf.amount') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lines as $i => $line)
            @php
                $order = $line->order;
                $tx = $line->transaction;
                $type = $tx?->transaction_type instanceof \App\Enums\DriverTransactionType
                    ? $tx->transaction_type
                    : ($tx ? \App\Enums\DriverTransactionType::tryFrom((string) $tx->transaction_type) : null);
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $order?->tracking_number ?? ($order ? '#'.$order->id : '—') }}</td>
                <td class="@bidiclass($order?->customer_full_name)">@bidilines($order?->customer_full_name ?? '—', 18)</td>
                <td class="@bidiclass($order?->city?->name)">@bidilines($order?->city?->name ?? '—', 14)</td>
                <td class="@bidiclass($line->sector?->name)">@bidilines($line->sector?->name ?? '—', 14)</td>
                <td><span class="badge">{{ $type?->label() ?? '—' }}</span></td>
                <td class="num">{{ $money($line->amount_snapshot) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="row clearfix">
        <table class="totals">
            <tr>
                <td class="label">{{ __('driver_invoices.pdf.deliveries_count') }}</td>
                <td class="value">{{ $invoice->deliveries_count }}</td>
            </tr>
            <tr class="net">
                <td class="label">{{ __('driver_invoices.pdf.total_earned') }}</td>
                <td class="value">{{ $money($invoice->total_amount) }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- The "page x of y" that belongs on this line is stamped by
     PdfDriverInvoiceService: only the renderer knows how many sheets there are. --}}
<div class="footer clearfix">
    <span style="float:left;">{{ $invoice->invoice_number }}</span>
    <span style="float:right;">{{ __('driver_invoices.pdf.generated_on') }}: {{ optional($invoice->generated_at)->format('Y-m-d H:i') }}</span>
</div>
</body>
</html>
