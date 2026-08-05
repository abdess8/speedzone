<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF as PdfInstance;

/**
 * Stamps "page x / y" in the footer band of a rendered document.
 *
 * A settlement with sixty orders runs over three sheets, and three loose sheets
 * on a desk are indistinguishable without a number on them. It cannot be done
 * from the Blade template: the page count only exists once the layout has been
 * resolved, which is why the document is rendered here and the number written
 * straight onto the canvas afterwards.
 *
 * The templates reserve the band through their `@page` bottom margin, so the
 * text never lands on top of a row.
 */
final class PdfPageNumbering
{
    /** Distance from the bottom edge of the paper to the footer line, in points. */
    private const FOOTER_BASELINE = 46;

    private const SIZE = 8;

    private const COLOUR = [0.6, 0.64, 0.7];

    /**
     * @param  string  $translationKey  Message taking `:page` and `:total`.
     */
    public static function stamp(PdfInstance $pdf, string $translationKey): PdfInstance
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');

        $canvas->page_script(function (int $page, int $total) use ($canvas, $metrics, $font, $translationKey): void {
            $text = __($translationKey, ['page' => $page, 'total' => $total]);

            // Centred between the two ends of the footer line, which the
            // template lays out on either side.
            $canvas->text(
                ($canvas->get_width() - $metrics->getTextWidth($text, $font, self::SIZE)) / 2,
                $canvas->get_height() - self::FOOTER_BASELINE,
                $text,
                $font,
                self::SIZE,
                self::COLOUR,
            );
        });

        return $pdf;
    }
}
