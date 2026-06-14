<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Delivery Note — {{ $pickup->reference }}</title>
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
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if ($logo)
                    <img src="{{ $logo }}" class="logo" alt="logo">
                @else
                    <strong>{{ $companyName }}</strong>
                @endif
            </td>
            <td style="text-align: right;">
                <p class="title">Delivery Note / Bon de Ramassage</p>
                <div class="meta"><strong>Reference:</strong> {{ $pickup->reference }}</div>
                <div class="meta"><strong>Date:</strong> {{ $pickup->created_at?->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Pickup Information</div>
        <strong>Address:</strong> {{ $pickup->pickup_address }}<br>
        <strong>Packages:</strong> {{ $pickup->number_of_packages }} &nbsp;|&nbsp;
        <strong>Total Amount:</strong> {{ number_format((float) $pickup->total_orders_amount, 2) }} MAD
    </div>

    <div class="section">
        <div class="section-title">Seller</div>
        {{ $pickup->creator?->full_name ?? '—' }}<br>
        {{ $pickup->creator?->email ?? '' }} &nbsp; {{ $pickup->creator?->phone_number ?? '' }}
    </div>

    <div class="section">
        <div class="section-title">Driver</div>
        {{ $pickup->assignee?->full_name ?? 'Not assigned yet' }}<br>
        {{ $pickup->assignee?->phone_number ?? '' }}
    </div>

    <div class="section">
        <div class="section-title">Orders</div>
        <table>
            <thead>
                <tr>
                    <th>Tracking #</th>
                    <th>Customer</th>
                    <th>City</th>
                    <th>Sector</th>
                    <th style="text-align:right;">Amount (MAD)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>{{ $order->tracking_number }}</td>
                        <td>{{ $order->customer_full_name }}</td>
                        <td>{{ $order->city?->name ?? '—' }}</td>
                        <td>{{ $order->sector?->name ?? '—' }}</td>
                        <td style="text-align:right;">{{ number_format((float) $order->order_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($pickup->notes)
        <div class="section">
            <div class="section-title">Notes</div>
            {{ $pickup->notes }}
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-box">Driver Signature</div>
            </td>
            <td>
                <div class="sign-box">Seller Signature</div>
            </td>
        </tr>
    </table>
</body>
</html>
