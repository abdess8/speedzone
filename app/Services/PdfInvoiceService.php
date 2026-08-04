<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\PdfPageNumbering;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Illuminate\Support\Facades\Storage;

class PdfInvoiceService
{
    /**
     * Disk and folder where generated invoice PDFs are stored.
     */
    private const DISK = 'public';

    private const FOLDER = 'invoices';

    /**
     * Build the invoice PDF (A4) without persisting it.
     */
    public function build(Invoice $invoice): PdfInstance
    {
        $invoice->loadMissing([
            'seller.city',
            'invoiceOrders.order.city',
            'invoiceOrders.order.sector',
        ]);

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'seller' => $invoice->seller,
            'lines' => $invoice->invoiceOrders,
            'logo' => $this->logoDataUri(),
            'companyName' => config('orders.label.company_name', 'OWL Delivery'),
        ])->setPaper('a4');

        return PdfPageNumbering::stamp($pdf, 'invoices.pdf.page_of');
    }

    /**
     * Build and persist the PDF on the public disk, returning the stored path.
     */
    public function store(Invoice $invoice): string
    {
        $path = self::FOLDER.'/'.$this->fileName($invoice);

        Storage::disk(self::DISK)->put($path, $this->build($invoice)->output());

        $invoice->forceFill(['pdf_file' => $path])->save();

        return $path;
    }

    public function fileName(Invoice $invoice): string
    {
        return $invoice->invoice_number.'.pdf';
    }

    /**
     * Absolute path to a previously stored PDF, or null when missing.
     */
    public function storedPath(Invoice $invoice): ?string
    {
        if (! $invoice->pdf_file || ! Storage::disk(self::DISK)->exists($invoice->pdf_file)) {
            return null;
        }

        return Storage::disk(self::DISK)->path($invoice->pdf_file);
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
