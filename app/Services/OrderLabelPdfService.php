<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class OrderLabelPdfService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
        private readonly BarcodeService $barcodeService,
        private readonly LabelIconService $iconService,
    ) {}

    /**
     * Build the thermal shipping-label PDF for an order.
     */
    public function build(Order $order): PdfInstance
    {
        $order->loadMissing(['city', 'sector', 'seller', 'store']);

        $width = (float) config('orders.label.paper_width', 283);
        $height = (float) config('orders.label.paper_height', 425);

        return Pdf::loadView('orders.label', [
            'order' => $order,
            'qrCode' => $this->qrCodeService->dataUri($order->trackingUrl(), 220, 4),
            'barcode' => $this->barcodeService->code128DataUri((string) $order->tracking_number),
            'icons' => $this->iconService->labelIcons(),
            ...$this->branding($order),
        ])->setPaper([0, 0, $width, $height]);
    }

    /**
     * Build a single PDF containing one label per page for several orders.
     *
     * @param  Collection<int, Order>  $orders
     */
    public function buildBatch(Collection $orders): PdfInstance
    {
        $orders->loadMissing(['city', 'sector', 'seller', 'store']);

        $width = (float) config('orders.label.paper_width', 283);
        $height = (float) config('orders.label.paper_height', 425);

        // Branding is resolved per label, not once for the batch: an admin
        // printing a mixed selection must get each package's own store on it.
        $labels = $orders->map(fn (Order $order) => [
            'order' => $order,
            'qrCode' => $this->qrCodeService->dataUri($order->trackingUrl(), 220, 4),
            'barcode' => $this->barcodeService->code128DataUri((string) $order->tracking_number),
            ...$this->branding($order),
        ])->all();

        return Pdf::loadView('orders.labels', [
            'labels' => $labels,
            'icons' => $this->iconService->labelIcons(),
        ])->setPaper([0, 0, $width, $height]);
    }

    public function fileName(Order $order): string
    {
        return 'label-'.$order->tracking_number.'.pdf';
    }

    /**
     * Name and logo printed on the label.
     *
     * The order's store wins over the platform defaults, so a package leaves
     * the depot branded with the shop that sold it. Falls back to the OWL Delivery
     * identity for orders with no store (partner ingestion, legacy rows).
     *
     * @return array{logo: string|null, companyName: string}
     */
    private function branding(Order $order): array
    {
        $store = $order->store;

        return [
            'logo' => $this->storeLogoDataUri($store?->logo_path) ?? $this->logoDataUri(),
            'companyName' => $store?->name ?: config('orders.label.company_name', 'OWL Delivery'),
        ];
    }

    /**
     * Encode a store logo (a user upload on the public disk) as a data URI.
     */
    private function storeLogoDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$this->mimeFor($path).';base64,'.base64_encode((string) $disk->get($path));
    }

    /**
     * Encode the platform logo as a data URI so dompdf can embed it reliably.
     */
    private function logoDataUri(): ?string
    {
        $path = config('orders.label.logo_path');

        if (! $path || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return 'data:'.$this->mimeFor($path).';base64,'.base64_encode((string) file_get_contents($path));
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
