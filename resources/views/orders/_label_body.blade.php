@php
    $cashCollection = $order->payment_method->requiresCashCollection();
    $destination = $order->city?->name;
    $zone = $order->sector?->name ?: $destination;
@endphp

<div class="label">
    <table class="brand">
        <tr>
            <td class="brand-icon-cell"><img src="{{ $icons['speed'] }}" class="brand-icon" alt=""></td>
            <td class="brand-name">
                @if ($logo)
                    <img src="{{ $logo }}" class="brand-logo" alt="{{ $companyName }}">
                @else
                    @bidi($companyName)
                @endif
            </td>
            <td class="brand-icon-cell"><img src="{{ $icons['speed'] }}" class="brand-icon" alt=""></td>
        </tr>
    </table>

    <div class="rule"></div>

    <div class="tracking">{{ $order->tracking_number }}</div>
    <div class="barcode"><img src="{{ $barcode }}" alt="{{ $order->tracking_number }}"></div>

    <table class="recipient-row">
        <tr>
            <td class="qr-cell">
                <img src="{{ $qrCode }}" alt="QR">
                <div class="qr-caption">{{ __('orders.label_pdf.scan_to_track') }}</div>
            </td>
            <td>
                <div class="recipient">
                    <table class="recipient-head">
                        <tr>
                            <td>
                                <div class="recipient-title">{{ __('orders.label_pdf.recipient_details') }}</div>
                                <div class="recipient-name @bidiclass($order->customer_full_name)">@bidi(Str::limit($order->customer_full_name, 22))</div>
                                <div class="recipient-line">{{ Str::limit($order->customer_phone, 24) }}</div>
                                <div class="recipient-line @bidiclass($order->customer_address)">@bidilines(Str::limit($order->customer_address, 48), 38)</div>
                            </td>
                            @if ($destination)
                                <td class="pin-cell">
                                    <img src="{{ $icons['pin'] }}" alt="">
                                    <div class="pin-label">@bidilines(Str::limit($destination, 14), 8)</div>
                                </td>
                            @endif
                        </tr>
                    </table>
                    <div class="recipient-city @bidiclass($destination)">
                        @bidi(Str::limit($destination.($order->sector ? ' · '.$order->sector->name : ''), 24))
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="details">
        <div class="details-title">{{ __('orders.label_pdf.order_details') }}</div>
        <table class="details-grid">
            <tr>
                <td class="first">
                    <div class="details-label">{{ __('orders.label_pdf.order_date') }}</div>
                    <div class="details-value">{{ $order->created_at?->format('Y-m-d') ?: '—' }}</div>
                </td>
                <td class="split">
                    <div class="details-label">{{ __('orders.label_pdf.delivery_zone') }}</div>
                    <div class="details-value @bidiclass($zone)">@bidi(Str::limit($zone ?: '—', 18))</div>
                </td>
                <td class="split last">
                    <div class="details-label">{{ __('orders.label_pdf.notes') }}</div>
                    <div class="details-value @bidiclass($order->notes)">@bidi(Str::limit($order->notes ?: '—', 30))</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="totals">
        <tr>
            <td>
                <div class="totals-label">{{ __('orders.label_pdf.payment_method') }}</div>
                <img src="{{ $cashCollection ? $icons['cash'] : $icons['card'] }}" class="pay-icon" alt="">
                <span class="pay-name">{{ $order->payment_method->label() }}</span>
            </td>
            <td class="amount-cell">
                <div class="amount-label">{{ __('orders.label_pdf.total') }}</div>
                <div class="amount-value">{{ number_format((float) $order->total_amount, 2) }} MAD</div>
            </td>
        </tr>
    </table>

    <div class="flags">
        <span class="flag {{ $order->is_fragile ? 'on' : '' }}">
            {{ $order->is_fragile ? __('orders.label_pdf.fragile') : __('orders.label_pdf.not_fragile') }}
        </span>
        <span class="flag {{ $order->can_be_opened ? '' : 'on' }}">
            {{ $order->can_be_opened ? __('orders.label_pdf.openable') : __('orders.label_pdf.do_not_open') }}
        </span>
    </div>

    <table class="foot">
        <tr>
            <td class="foot-icon-cell"><img src="{{ $icons['speed_muted'] }}" class="foot-icon" alt=""></td>
            <td>
                {{ __('orders.label_pdf.seller') }}: @bidi($order->seller?->full_name) &middot; {{ $order->trackingUrl() }}
            </td>
        </tr>
    </table>
</div>
