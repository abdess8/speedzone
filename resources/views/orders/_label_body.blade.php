<div class="label">
    <table class="header" width="100%">
        <tr>
            <td>
                @if ($logo)
                    <img src="{{ $logo }}" class="logo" alt="logo">
                @else
                    <span class="company">{{ $companyName }}</span>
                @endif
            </td>
            <td style="text-align: right;">
                <span class="company">{{ $companyName }}</span><br>
                <span class="meta">{{ $order->created_at?->format('Y-m-d H:i') }}</span>
            </td>
        </tr>
    </table>

    <div class="tracking">{{ $order->tracking_number }}</div>

    <table class="qr-row" width="100%">
        <tr>
            <td class="qr">
                <img src="{{ $qrCode }}" alt="QR">
                <div class="meta">{{ __('orders.label_pdf.scan_to_track') }}</div>
            </td>
            <td>
                <div class="section-title">{{ __('orders.label_pdf.deliver_to') }}</div>
                <div class="name">{{ $order->customer_full_name }}</div>
                <div class="phone">{{ $order->customer_phone }}</div>
                <div class="address">{{ $order->customer_address }}</div>
                <div class="city">{{ $order->city?->name }}@if ($order->sector) &middot; {{ $order->sector->name }}@endif</div>
            </td>
        </tr>
    </table>

    @if ($order->payment_method->requiresCashCollection())
        <div class="collection cash-required">
            <div class="collection-title">{{ __('orders.label_pdf.payment_required') }}</div>
            <div class="collection-row">{{ __('orders.label_pdf.cash_collection') }}: <strong>{{ __('orders.label_pdf.yes') }}</strong></div>
            <div class="collection-amount">
                {{ __('orders.label_pdf.amount_to_collect') }}:
                <strong>{{ number_format((float) $order->order_amount, 2) }} MAD</strong>
            </div>
        </div>
    @else
        <div class="collection card-paid">
            <div class="collection-title">{{ __('orders.label_pdf.already_paid') }}</div>
            <div class="collection-row">{{ __('orders.label_pdf.cash_collection') }}: <strong>{{ __('orders.label_pdf.no') }}</strong></div>
        </div>
    @endif

    <table class="amounts">
        <tr>
            <td>{{ __('orders.label_pdf.payment_method') }}</td>
            <td style="text-align:right;">
                <span class="pay">{{ $order->payment_method->displayLabel() }}</span>
            </td>
        </tr>
        @if ($order->order_value !== null)
            <tr>
                <td>{{ __('orders.label_pdf.order_value') }}</td>
                <td style="text-align:right;">{{ number_format((float) $order->order_value, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td>{{ __('orders.label_pdf.delivery_price') }}</td>
            <td style="text-align:right;">{{ number_format((float) $order->delivery_price, 2) }}</td>
        </tr>
        <tr>
            <td class="total">{{ __('orders.label_pdf.total') }}</td>
            <td class="total" style="text-align:right;">{{ number_format((float) $order->total_amount, 2) }}</td>
        </tr>
    </table>

    <div class="flags">
        <span class="flag {{ $order->is_fragile ? 'on' : '' }}">
            {{ $order->is_fragile ? __('orders.label_pdf.fragile') : __('orders.label_pdf.not_fragile') }}
        </span>
        <span class="flag {{ $order->can_be_opened ? 'on' : '' }}">
            {{ $order->can_be_opened ? __('orders.label_pdf.openable') : __('orders.label_pdf.do_not_open') }}
        </span>
    </div>

    @if ($order->notes)
        <div class="section-title" style="margin-top:6px;">{{ __('orders.label_pdf.notes') }}</div>
        <div class="notes">{{ $order->notes }}</div>
    @endif

    <div class="foot">
        {{ __('orders.label_pdf.seller') }}: {{ $order->seller?->full_name }} &middot; {{ $order->trackingUrl() }}
    </div>
</div>
