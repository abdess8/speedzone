<?php /** @var \App\Models\DriverInvoice $invoice */ ?>
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
        .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: bold; background: #d1f5e0; color: #0a7d3f; }
        .totals { width: 46%; float: right; margin-top: 12px; }
        .totals td { padding: 5px 8px; }
        .totals .label { color: #56607a; }
        .totals .value { text-align: right; font-weight: bold; }
        .totals .net td { border-top: 2px solid #0d6efd; font-size: 14px; color: #0d6efd; padding-top: 8px; }
        .status-chip { display:inline-block; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; background:#e7f1ff; color:#0d6efd; }
        .footer { position: fixed; bottom: 18px; left: 32px; right: 32px; color: #98a2b3; font-size: 9px; border-top: 1px solid #e5e8ef; padding-top: 6px; }
    </style>
</head>
<body>
@php
    $money = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $status = $invoice->status instanceof \App\Enums\DriverInvoiceStatus ? $invoice->status : \App\Enums\DriverInvoiceStatus::from($invoice->status);
@endphp
<div class="wrap">
    <div class="row clearfix">
        <div class="col-left">
            @if($logo)
                <img src="{{ $logo }}" class="logo" alt="logo">
            @else
                <div class="brand">{{ $companyName }}</div>
            @endif
            <div class="muted mt-2">{{ $companyName }}</div>
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
                <div class="name">{{ $driver->full_name }}</div>
                @if($driver->phone_number)<div class="muted">{{ $driver->phone_number }}</div>@endif
                @if($driver->cin)<div class="muted">CIN: {{ $driver->cin }}</div>@endif
                @if($driver->address)<div class="muted">{{ $driver->address }}</div>@endif
            </div>
        </div>
        <div class="col-right" style="text-align:left;">
            <div class="panel">
                <h4>{{ __('driver_invoices.pdf.payment_details') }}</h4>
                @if($driver->bank_name)<div><span class="muted">{{ __('driver_invoices.pdf.bank') }}:</span> {{ $driver->bank_name }}</div>@endif
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
                <td>{{ $order?->customer_full_name ?? '—' }}</td>
                <td>{{ $order?->city?->name ?? '—' }}</td>
                <td>{{ $line->sector?->name ?? '—' }}</td>
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

<div class="footer clearfix">
    <span style="float:left;">{{ $invoice->invoice_number }}</span>
    <span style="float:right;">{{ __('driver_invoices.pdf.generated_on') }}: {{ optional($invoice->generated_at)->format('Y-m-d H:i') }}</span>
</div>
</body>
</html>
