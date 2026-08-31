<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Placement of the covered cities on the landing page's Morocco map.
 *
 * The map is a plain SVG outline, so every city is positioned with the
 * percentages of the container it sits at. They come from the same linear
 * projection as the outline itself (x = 96.5 + 5.132 · lon, y = 213.6 −
 * 5.667 · lat), which is why they must not be "rounded to something nicer":
 * the pins would drift off the coastline.
 *
 * The eight headline cities also carry a `chip` anchor — the point where their
 * name + price is drawn. Those anchors are hand-placed so the chips never
 * overlap; every other city is still plotted, just without a permanent chip
 * (its price shows on hover and in the price grid).
 */
final class MoroccoCityMap
{
    /**
     * Keys are the normalised city names produced by {@see normalise()}.
     *
     * `rates_from` names the city whose tariff a destination inherits when it
     * has no sector of its own yet — Dakhla is served on the Laâyoune leg.
     *
     * @var array<string, array{label: string, x: float, y: float, chip?: array{x: float, y: float}, region?: string, rates_from?: string}>
     */
    private const CITIES = [
        'TANGER' => ['label' => 'Tanger', 'x' => 66.6, 'y' => 11.1, 'chip' => ['x' => 49.0, 'y' => 9.5]],
        'AL HOCEIMA' => ['label' => 'Al Hoceïma', 'x' => 76.3, 'y' => 13.9],
        'NADOR' => ['label' => 'Nador', 'x' => 81.5, 'y' => 14.3],
        'OUJDA' => ['label' => 'Oujda', 'x' => 86.7, 'y' => 17.1, 'chip' => ['x' => 85.0, 'y' => 26.0]],
        'KENITRA' => ['label' => 'Kénitra', 'x' => 62.7, 'y' => 19.4],
        'SALE' => ['label' => 'Salé', 'x' => 61.6, 'y' => 20.6],
        'RABAT' => ['label' => 'Rabat', 'x' => 61.4, 'y' => 20.8, 'chip' => ['x' => 42.0, 'y' => 19.0]],
        'TEMARA' => ['label' => 'Témara', 'x' => 61.1, 'y' => 21.3],
        'FES' => ['label' => 'Fès', 'x' => 70.8, 'y' => 20.8, 'chip' => ['x' => 81.0, 'y' => 16.0]],
        'MEKNES' => ['label' => 'Meknès', 'x' => 68.0, 'y' => 21.5],
        'CASABLANCA' => ['label' => 'Casablanca', 'x' => 57.6, 'y' => 23.3, 'chip' => ['x' => 36.0, 'y' => 28.5]],
        'BERRECHID' => ['label' => 'Berrechid', 'x' => 57.6, 'y' => 25.1],
        'EL JADIDA' => ['label' => 'El Jadida', 'x' => 52.8, 'y' => 25.2],
        'SETTAT' => ['label' => 'Settat', 'x' => 57.4, 'y' => 26.6],
        'KHOURIBGA' => ['label' => 'Khouribga', 'x' => 61.1, 'y' => 27.3],
        'KHENIFRA' => ['label' => 'Khénifra', 'x' => 67.4, 'y' => 26.9],
        'BENI MELLAL' => ['label' => 'Béni Mellal', 'x' => 63.9, 'y' => 30.4],
        'SAFI' => ['label' => 'Safi', 'x' => 49.1, 'y' => 30.6],
        'MARRAKECH' => ['label' => 'Marrakech', 'x' => 55.5, 'y' => 34.4, 'chip' => ['x' => 33.0, 'y' => 38.0]],
        'OUARZAZATE' => ['label' => 'Ouarzazate', 'x' => 60.9, 'y' => 38.3],
        'AGADIR' => ['label' => 'Agadir', 'x' => 47.2, 'y' => 41.2, 'chip' => ['x' => 29.0, 'y' => 47.5]],
        'LAAYOUNE' => ['label' => 'Laâyoune', 'x' => 28.8, 'y' => 59.8],
        'DAKHLA' => [
            'label' => 'Dakhla',
            'x' => 14.7,
            'y' => 77.6,
            'chip' => ['x' => 27.0, 'y' => 77.6],
            'region' => 'DAKHLA-OUED EDDAHAB',
            'rates_from' => 'LAAYOUNE',
        ],
    ];

    /**
     * Display names of the twelve administrative regions.
     *
     * @var array<string, string>
     */
    private const REGIONS = [
        'TANGER-TETOUAN-AL HOCEIMA' => 'Tanger-Tétouan-Al Hoceïma',
        'ORIENTAL' => 'Oriental',
        'FES-MEKNES' => 'Fès-Meknès',
        'RABAT-SALE-KENITRA' => 'Rabat-Salé-Kénitra',
        'BENI MELLAL-KHENIFRA' => 'Béni Mellal-Khénifra',
        'CASABLANCA-SETTAT' => 'Casablanca-Settat',
        'MARRAKECH-SAFI' => 'Marrakech-Safi',
        'DARAA-TAFILELT' => 'Drâa-Tafilalet',
        'SOUSS-MASSA' => 'Souss-Massa',
        'GUELMIM-OUED NOUN' => 'Guelmim-Oued Noun',
        'LAAYOUN-DAKHLA' => 'Laâyoune-Sakia El Hamra',
        'DAKHLA-OUED EDDAHAB' => 'Dakhla-Oued Ed-Dahab',
    ];

    /**
     * Reduce a stored city name to the key shared by its "ville" and "région"
     * rows, e.g. `CASABLANCA VILLE`, `CASABLANCA REGION` → `CASABLANCA`.
     */
    public static function normalise(string $name): string
    {
        $name = Str::upper(Str::ascii(trim($name)));
        $name = preg_replace('/\b(VILLE|REGION)\b/', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    /**
     * Map placement for a normalised city key, or null when we have no
     * coordinates for it (it then only appears in the price grid).
     *
     * @return array{label: string, x: float, y: float, chip?: array{x: float, y: float}, region?: string, rates_from?: string}|null
     */
    public static function place(string $key): ?array
    {
        return self::CITIES[$key] ?? null;
    }

    /**
     * Cities we plot even when no sector references them, by inheriting the
     * tariff of the city they are served from.
     *
     * @return array<string, array{label: string, x: float, y: float, chip?: array{x: float, y: float}, region?: string, rates_from: string}>
     */
    public static function inherited(): array
    {
        return array_filter(self::CITIES, fn (array $city) => isset($city['rates_from']));
    }

    /**
     * Human-readable city name, falling back to a title-cased stored name.
     */
    public static function label(string $key): string
    {
        return self::CITIES[$key]['label'] ?? Str::title(Str::lower($key));
    }

    /**
     * Stable key for a stored region name, used to translate it client-side.
     */
    public static function regionKey(string $region): string
    {
        return Str::upper(Str::ascii(trim($region)));
    }

    /**
     * Human-readable region name, falling back to a title-cased stored name.
     */
    public static function region(string $region): string
    {
        return self::REGIONS[self::regionKey($region)] ?? Str::title(Str::lower($region));
    }
}
