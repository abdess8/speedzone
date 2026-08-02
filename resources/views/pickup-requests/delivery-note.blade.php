<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('pickups.delivery_note_pdf.title') }} — {{ $pickup->reference }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #0D4A9D; padding-bottom: 10px; }
        .logo { max-height: 50px; }
        .title { font-size: 20px; font-weight: bold; color: #0D4A9D; margin: 0; }
        .meta { margin-top: 8px; }
        .section { margin-bottom: 16px; }
        .section-title { font-size: 13px; font-weight: bold; margin-bottom: 6px; text-transform: uppercase; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .signatures { margin-top: 40px; width: 100%; }
        .signatures td { border: none; width: 50%; vertical-align: top; }
        .sign-box { border-top: 1px solid #333; margin-top: 50px; padding-top: 6px; }
        /* Arabic values are emitted in visual order, so they only need to hang
           right. Table cells additionally refuse to wrap: a break decided by the
           engine would put the end of the value on the first line. */
        .rtl { text-align: right; }
        td.rtl { white-space: nowrap; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if ($logo)
                    <img src="{{ $logo }}" class="logo" alt="logo">
                @else
                    <strong>@bidi($companyName)</strong>
                @endif
            </td>
            <td style="text-align: right;">
                <p class="title">{{ __('pickups.delivery_note_pdf.title') }}</p>
                <div class="meta"><strong>{{ __('pickups.delivery_note_pdf.reference') }}:</strong> {{ $pickup->reference }}</div>
                <div class="meta"><strong>{{ __('pickups.delivery_note_pdf.date') }}:</strong> {{ $pickup->created_at?->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">{{ __('pickups.delivery_note_pdf.pickup_info') }}</div>
        <strong>{{ __('pickups.delivery_note_pdf.address') }}:</strong> @bidilines($pickup->pickup_address, 80)<br>
        <strong>{{ __('pickups.delivery_note_pdf.packages') }}:</strong> {{ $pickup->number_of_packages }} &nbsp;|&nbsp;
        <strong>{{ __('pickups.delivery_note_pdf.total_amount') }}:</strong> {{ number_format((float) $pickup->total_orders_amount, 2) }} MAD
    </div>

    <div class="section">
        <div class="section-title">{{ __('pickups.delivery_note_pdf.seller') }}</div>
        @bidi($pickup->creator?->full_name ?? '—')<br>
        {{ $pickup->creator?->email ?? '' }} &nbsp; {{ $pickup->creator?->phone_number ?? '' }}
    </div>

    <div class="section">
        <div class="section-title">{{ __('pickups.delivery_note_pdf.driver') }}</div>
        @bidi($pickup->assignee?->full_name ?? __('pickups.delivery_note_pdf.not_assigned'))<br>
        {{ $pickup->assignee?->phone_number ?? '' }}
    </div>

    <div class="section">
        <div class="section-title">{{ __('pickups.delivery_note_pdf.orders') }}</div>
        <table>
            <thead>
                <tr>
                    <th>{{ __('pickups.delivery_note_pdf.tracking_number') }}</th>
                    <th>{{ __('pickups.delivery_note_pdf.customer') }}</th>
                    <th>{{ __('pickups.delivery_note_pdf.city') }}</th>
                    <th>{{ __('pickups.delivery_note_pdf.sector') }}</th>
                    <th style="text-align:right;">{{ __('pickups.delivery_note_pdf.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>{{ $order->tracking_number }}</td>
                        <td class="@bidiclass($order->customer_full_name)">@bidilines($order->customer_full_name, 22)</td>
                        <td class="@bidiclass($order->city?->name)">@bidilines($order->city?->name ?? '—', 16)</td>
                        <td class="@bidiclass($order->sector?->name)">@bidilines($order->sector?->name ?? '—', 16)</td>
                        <td style="text-align:right;">{{ number_format((float) $order->order_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($pickup->notes)
        <div class="section">
            <div class="section-title">{{ __('pickups.delivery_note_pdf.notes') }}</div>
            <div class="@bidiclass($pickup->notes)">@bidilines($pickup->notes, 80)</div>
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-box">{{ __('pickups.delivery_note_pdf.driver_signature') }}</div>
            </td>
            <td>
                <div class="sign-box">{{ __('pickups.delivery_note_pdf.seller_signature') }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
