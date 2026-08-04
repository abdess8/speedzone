<?php

namespace App\Services;

use App\Models\PickupRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;

class PickupDeliveryNotePdfService
{
    /**
     * Build the pickup delivery note PDF (bon de livraison / ramassage).
     */
    public function build(PickupRequest $pickup): PdfInstance
    {
        $pickup->loadMissing(['creator', 'assignee', 'orders.city', 'orders.sector']);

        return Pdf::loadView('pickup-requests.delivery-note', [
            'pickup' => $pickup,
            'orders' => $pickup->orders,
            'logo' => $this->logoDataUri(),
            'companyName' => config('pickup.delivery_note.company_name', 'OWL Delivery'),
        ])->setPaper(config('pickup.delivery_note.paper', 'a4'), 'portrait');
    }

    public function fileName(PickupRequest $pickup): string
    {
        return 'delivery-note-'.$pickup->reference.'.pdf';
    }

    private function logoDataUri(): ?string
    {
        $path = config('pickup.delivery_note.logo_path');

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
