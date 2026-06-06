<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Illuminate\Support\Collection;

class OrderLabelPdfService
{
    public function __construct(private readonly QrCodeService $qrCodeService) {}

    /**
     * Build the thermal shipping-label PDF for an order.
     */
    public function build(Order $order): PdfInstance
    {
        $order->loadMissing(['city', 'sector', 'seller']);

        $width = (float) config('orders.label.paper_width', 283);
        $height = (float) config('orders.label.paper_height', 425);

        return Pdf::loadView('orders.label', [
            'order' => $order,
            'qrCode' => $this->qrCodeService->dataUri($order->trackingUrl(), 220, 4),
            'logo' => $this->logoDataUri(),
            'companyName' => config('orders.label.company_name', 'SpeedZone Express'),
        ])->setPaper([0, 0, $width, $height]);
    }

    /**
     * Build a single PDF containing one label per page for several orders.
     *
     * @param  Collection<int, Order>  $orders
     */
    public function buildBatch(Collection $orders): PdfInstance
    {
        $orders->loadMissing(['city', 'sector', 'seller']);

        $width = (float) config('orders.label.paper_width', 283);
        $height = (float) config('orders.label.paper_height', 425);

        $labels = $orders->map(fn (Order $order) => [
            'order' => $order,
            'qrCode' => $this->qrCodeService->dataUri($order->trackingUrl(), 220, 4),
        ])->all();

        return Pdf::loadView('orders.labels', [
            'labels' => $labels,
            'logo' => $this->logoDataUri(),
            'companyName' => config('orders.label.company_name', 'SpeedZone Express'),
        ])->setPaper([0, 0, $width, $height]);
    }

    public function fileName(Order $order): string
    {
        return 'label-'.$order->tracking_number.'.pdf';
    }

    /**
     * Encode the company logo as a data URI so dompdf can embed it reliably.
     */
    private function logoDataUri(): ?string
    {
        $path = config('orders.label.logo_path');

        if (! $path || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
