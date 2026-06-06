<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    /**
     * Build a PNG QR code and return it as a base64 data URI.
     *
     * PNG (GD based) is the most reliable format for embedding inside dompdf.
     */
    public function dataUri(string $data, int $size = 220, int $margin = 8): string
    {
        return $this->build($data, new PngWriter, $size, $margin)->getDataUri();
    }

    /**
     * Build a QR code and return the raw binary string (PNG by default).
     */
    public function binary(string $data, int $size = 300, int $margin = 10): string
    {
        return $this->build($data, new PngWriter, $size, $margin)->getString();
    }

    /**
     * Build an inline SVG string (useful for crisp on-screen rendering).
     */
    public function svg(string $data, int $size = 220, int $margin = 8): string
    {
        return $this->build($data, new SvgWriter, $size, $margin)->getString();
    }

    private function build(string $data, PngWriter|SvgWriter $writer, int $size, int $margin)
    {
        return (new Builder(
            writer: $writer,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: $margin,
        ))->build();
    }
}
