<?php

namespace App\Services;

use App\Models\DriverInvoice;
use App\Support\PdfPageNumbering;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Illuminate\Support\Facades\Storage;

class DriverPdfService
{
    private const DISK = 'public';

    private const FOLDER = 'driver-invoices';

    /**
     * Build the driver invoice PDF (A4) without persisting it.
     */
    public function build(DriverInvoice $invoice): PdfInstance
    {
        $invoice->loadMissing([
            'driver.city',
            'invoiceTransactions.driverTransaction.order.city',
            'invoiceTransactions.driverTransaction.order.sector',
            'invoiceTransactions.driverTransaction.sector',
        ]);

        $lines = $invoice->invoiceTransactions->map(function ($pivot) {
            $tx = $pivot->driverTransaction;

            return (object) [
                'amount_snapshot' => $pivot->amount_snapshot,
                'transaction' => $tx,
                'order' => $tx?->order,
                'sector' => $tx?->sector ?? $tx?->order?->sector,
            ];
        });

        $pdf = Pdf::loadView('driver-invoices.pdf', [
            'invoice' => $invoice,
            'driver' => $invoice->driver,
            'lines' => $lines,
            'logo' => $this->logoDataUri(),
            'companyName' => config('orders.label.company_name', 'OWL Delivery'),
        ])->setPaper('a4');

        return PdfPageNumbering::stamp($pdf, 'driver_invoices.pdf.page_of');
    }

    /**
     * Build and persist the PDF on the public disk, returning the stored path.
     */
    public function store(DriverInvoice $invoice): string
    {
        $path = self::FOLDER.'/'.$this->fileName($invoice);

        Storage::disk(self::DISK)->put($path, $this->build($invoice)->output());

        $invoice->forceFill(['pdf_file' => $path])->save();

        return $path;
    }

    public function fileName(DriverInvoice $invoice): string
    {
        return $invoice->invoice_number.'.pdf';
    }

    /**
     * Absolute path to a previously stored PDF, or null when missing.
     */
    public function storedPath(DriverInvoice $invoice): ?string
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
