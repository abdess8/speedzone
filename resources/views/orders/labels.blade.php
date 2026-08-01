<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Shipping Labels</title>
    @include('orders._label_styles')
</head>
<body>
    @foreach ($labels as $index => $label)
        <div class="{{ $loop->last ? '' : 'page-break' }}">
            @include('orders._label_body', [
                'order' => $label['order'],
                'qrCode' => $label['qrCode'],
                'barcode' => $label['barcode'],
            ])
        </div>
    @endforeach
</body>
</html>
