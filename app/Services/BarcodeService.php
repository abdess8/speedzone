<?php

namespace App\Services;

class BarcodeService
{
    /**
     * Code 128 symbol table: each entry describes the widths of the
     * bar/space modules (starting with a bar) for the matching code value.
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /**
     * Render a Code 128-B barcode as a PNG data URI ready for dompdf.
     *
     * @param  int  $module  Width in pixels of the narrowest bar.
     * @param  int  $height  Bar height in pixels.
     * @param  int  $quietZone  Blank margin on both sides, expressed in modules.
     */
    public function code128DataUri(string $value, int $module = 2, int $height = 70, int $quietZone = 10): string
    {
        $bars = $this->modules($this->sanitize($value));
        $totalModules = array_sum(array_column($bars, 'width')) + ($quietZone * 2);

        $image = imagecreatetruecolor(max(1, $totalModules * $module), max(1, $height));
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, imagesx($image) - 1, $height - 1, $white);

        $cursor = $quietZone;

        foreach ($bars as $bar) {
            if ($bar['bar']) {
                imagefilledrectangle(
                    $image,
                    $cursor * $module,
                    0,
                    (($cursor + $bar['width']) * $module) - 1,
                    $height - 1,
                    $black
                );
            }

            $cursor += $bar['width'];
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * Expand the encoded symbols into an ordered list of bars and spaces.
     *
     * @return array<int, array{bar: bool, width: int}>
     */
    private function modules(string $value): array
    {
        $modules = [];

        foreach ($this->codes($value) as $code) {
            $isBar = true;

            foreach (str_split(self::PATTERNS[$code]) as $width) {
                $modules[] = ['bar' => $isBar, 'width' => (int) $width];
                $isBar = ! $isBar;
            }
        }

        return $modules;
    }

    /**
     * Build the symbol sequence: start B, payload, checksum and stop.
     *
     * @return array<int, int>
     */
    private function codes(string $value): array
    {
        $codes = [self::START_B];
        $checksum = self::START_B;

        foreach (str_split($value) as $position => $character) {
            $code = ord($character) - 32;
            $codes[] = $code;
            $checksum += ($position + 1) * $code;
        }

        $codes[] = $checksum % 103;
        $codes[] = self::STOP;

        return $codes;
    }

    /**
     * Code 128-B only covers ASCII 32-126; anything else is dropped.
     */
    private function sanitize(string $value): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';

        return $clean !== '' ? $clean : '0';
    }
}
