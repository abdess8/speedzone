<?php

namespace App\Services;

class TransferQrCodeService
{
    public function __construct(private readonly QrCodeService $qrCodes) {}

    public function dataUri(string $reference, int $size = 220): string
    {
        return $this->qrCodes->dataUri($this->scanUrl($reference), $size);
    }

    public function svg(string $reference, int $size = 220): string
    {
        return $this->qrCodes->svg($this->scanUrl($reference), $size);
    }

    public function scanUrl(string $reference): string
    {
        $base = rtrim((string) config('transfer.tracking_base_url', config('app.url')), '/');

        return $base.'/transfers/'.$reference;
    }
}
