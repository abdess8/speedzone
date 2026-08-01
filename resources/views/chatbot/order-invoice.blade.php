<?php /** @var \App\Models\Order $order */ ?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $order->tracking_number }}</title>
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
        .totals { width: 46%; float: right; margin-top: 12px; }
        .totals td { padding: 5px 8px; }
        .totals .label { color: #56607a; }
        .totals .value { text-align: right; font-weight: bold; }
        .totals .net td { border-top: 2px solid #0d6efd; font-size: 14px; color: #0d6efd; padding-top: 8px; }
        .status-chip { display: inline-block; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; background: #e7f1ff; color: #0d6efd; }
        .proforma { display: inline-block; margin-top: 6px; padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; background: #fff4e5; color: #ad6800; }
        .footer { position: fixed; bottom: 18px; left: 32px; right: 32px; color: #98a2b3; font-size: 9px; border-top: 1px solid #e5e8ef; padding-top: 6px; }
        .rtl { text-align: right; }
        table.items td.rtl { white-space: nowrap; }
    </style>
</head>
<body>
@php
    $money = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $currency = __('chatbot.pdf.currency');
    $status = $order->status instanceof \App\Enums\OrderStatus
        ? $order->status
        : \App\Enums\OrderStatus::from($order->status);
    $seller = $order->seller;
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
            <div class="doc-title">{{ $proforma ? __('chatbot.pdf.proforma_title') : __('chatbot.pdf.title') }}</div>
            <div class="mt-2"><strong>{{ $order->tracking_number }}</strong></div>
            <div class="muted">{{ __('chatbot.pdf.generated_on') }}: {{ now()->format('Y-m-d H:i') }}</div>
            <div class="mt-2"><span class="status-chip">{{ $status->label() }}</span></div>
            @if($proforma)
                <div><span class="proforma">{{ __('chatbot.pdf.proforma_notice') }}</span></div>
            @endif
        </div>
    </div>

    <div class="row clearfix mt-4">
        <div class="col-left">
            <div class="panel">
                <h4>{{ __('chatbot.pdf.seller') }}</h4>
                <div class="name @bidiclass($seller?->full_name)">@bidi($seller?->full_name ?? '—')</div>
                @if($seller?->ice_number)<div class="muted">ICE: {{ $seller->ice_number }}</div>@endif
                @if($seller?->phone_number)<div class="muted">{{ $seller->phone_number }}</div>@endif
                @if($seller?->city?->name)<div class="muted">{{ $seller->city->name }}</div>@endif
            </div>
        </div>
        <div class="col-right" style="text-align:left;">
            <div class="panel">
                <h4>{{ __('chatbot.pdf.recipient') }}</h4>
                <div class="name @bidiclass($order->customer_full_name)">@bidi($order->customer_full_name)</div>
                @if($order->customer_phone)<div class="muted">{{ $order->customer_phone }}</div>@endif
                @if($order->customer_address)<div class="muted @bidiclass($order->customer_address)">@bidilines($order->customer_address, 46)</div>@endif
                @if($order->city?->name)<div class="muted">{{ $order->city->name }}{{ $order->sector?->name ? ' — '.$order->sector->name : '' }}</div>@endif
            </div>
        </div>
    </div>

    <table class="items mt-4">
        <thead>
        <tr>
            <th>{{ __('chatbot.pdf.columns.description') }}</th>
            <th>{{ __('chatbot.pdf.columns.reference') }}</th>
            <th>{{ __('chatbot.pdf.columns.date') }}</th>
            <th class="num">{{ __('chatbot.pdf.columns.amount') }}</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ __('chatbot.pdf.columns.delivery_service') }}</td>
            <td>{{ $order->tracking_number }}</td>
            <td>{{ optional($order->completedAt() ?? $order->created_at)->format('Y-m-d') }}</td>
            <td class="num">{{ $money($line['order_amount']) }} {{ $currency }}</td>
        </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">{{ __('chatbot.pdf.totals.order_amount') }}</td>
            <td class="value">{{ $money($line['order_amount']) }} {{ $currency }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('chatbot.pdf.totals.delivery_fee') }}</td>
            <td class="value">- {{ $money($line['delivery_fee']) }} {{ $currency }}</td>
        </tr>
        @if($line['return_fee'] > 0)
            <tr>
                <td class="label">{{ __('chatbot.pdf.totals.return_fee') }}</td>
                <td class="value">- {{ $money($line['return_fee']) }} {{ $currency }}</td>
            </tr>
        @endif
        <tr class="net">
            <td class="label">{{ __('chatbot.pdf.totals.net') }}</td>
            <td class="value">{{ $money($line['final_amount']) }} {{ $currency }}</td>
        </tr>
    </table>
</div>

<div class="footer">
    {{ $companyName }} — {{ __('chatbot.pdf.footer') }}
</div>
</body>
</html>
