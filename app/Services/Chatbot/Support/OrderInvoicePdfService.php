<?php

namespace App\Services\Chatbot\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\BillingService;
use App\Services\PdfInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;

/**
 * Single-order invoice document.
 *
 * Seller invoices ({@see PdfInvoiceService}) settle a whole
 * period across many orders and are the accounting record. An order that has
 * not been settled yet still needs a printable statement — that is what this
 * produces, flagged as a proforma until the parcel reaches a billable status
 * so it can never be mistaken for the real invoice.
 */
class OrderInvoicePdfService
{
    public function __construct(private readonly BillingService $billing) {}

    public function build(Order $order): PdfInstance
    {
        $order->loadMissing(['city', 'sector', 'seller.city', 'driver']);

        return Pdf::loadView('chatbot.order-invoice', [
            'order' => $order,
            'line' => $this->line($order),
            'proforma' => ! $this->isBillable($order),
            'logo' => $this->logoDataUri(),
            'companyName' => config('orders.label.company_name', 'OWL Delivery'),
        ])->setPaper('a4');
    }

    public function fileName(Order $order): string
    {
        return ($this->isBillable($order) ? 'FACTURE-' : 'PROFORMA-').$order->tracking_number.'.pdf';
    }

    /**
     * Amounts printed on the document.
     *
     * A billable order reuses the exact billing math the seller invoice would
     * apply, so the two documents can never disagree.
     *
     * @return array{order_amount: float, delivery_fee: float, return_fee: float, final_amount: float}
     */
    private function line(Order $order): array
    {
        if ($this->isBillable($order)) {
            $line = $this->billing->computeLine($order);

            return [
                'order_amount' => $line['order_amount'],
                'delivery_fee' => $line['delivery_fee'],
                'return_fee' => $line['return_fee'],
                'final_amount' => $line['final_amount'],
            ];
        }

        $orderAmount = round((float) $order->order_amount, 2);
        $deliveryFee = round((float) $order->delivery_price, 2);

        return [
            'order_amount' => $orderAmount,
            'delivery_fee' => $deliveryFee,
            'return_fee' => 0.0,
            'final_amount' => round($orderAmount - $deliveryFee, 2),
        ];
    }

    private function isBillable(Order $order): bool
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        return in_array($status, [OrderStatus::DELIVERED, OrderStatus::RETURNED], true);
    }

    /**
     * Dompdf cannot fetch remote assets reliably, so the logo travels inline.
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
