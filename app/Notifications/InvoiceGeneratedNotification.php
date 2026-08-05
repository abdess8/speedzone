<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Invoice;

class InvoiceGeneratedNotification extends AppNotification
{
    public function __construct(public readonly Invoice $invoice) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::InvoiceGenerated;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $number = $this->invoice->invoice_number;
        $amount = number_format((float) $this->invoice->net_amount, 2);

        return $this->buildPayload([
            'title' => trans('notifications.titles.invoice_generated'),
            'message' => trans('notifications.messages.invoice_generated', [
                'reference' => $number,
            ]),
            'reference' => $number,
            'amount' => $amount,
            'url' => route('invoices.show', $this->invoice->id),
            'invoice_id' => $this->invoice->id,
        ]);
    }
}
