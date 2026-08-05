<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Shipping Label {{ $order->tracking_number }}</title>
    @include('orders._label_styles')
</head>
<body>
    @include('orders._label_body', ['order' => $order, 'qrCode' => $qrCode])
</body>
</html>
