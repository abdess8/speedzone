<?php

namespace Database\Seeders\Support;

use RuntimeException;

/**
 * Reads the commercial coverage grid, the source of truth for the cities we
 * serve, their sectors and the pricing attached to each sector.
 *
 * The grid is maintained as a spreadsheet by the commercial team and exported
 * verbatim to database/data/cities-sectors.csv, so refreshing the network is a
 * matter of replacing that file and re-running the seeders — no PHP to edit.
 */
class CitySectorDataset
{
    public const FILE = 'database/data/cities-sectors.csv';

    private const COLUMNS = [
        'city_name',
        'city_code',
        'region',
        'sector_name',
        'delivery_delay',
        'delivery_price',
        'return_price',
        'delivery_driver_price',
    ];

    /** @var array<int, array<string, string>>|null */
    private static ?array $rows = null;

    /**
     * Cities in the order they appear in the grid, deduplicated.
     *
     * @return array<int, array{name: string, code: string, region: string}>
     */
    public static function cities(): array
    {
        $cities = [];

        foreach (self::rows() as $row) {
            $cities[$row['city_name']] ??= [
                'name' => $row['city_name'],
                'code' => $row['city_code'],
                'region' => $row['region'],
            ];
        }

        return array_values($cities);
    }

    /**
     * Sectors grouped by the name of the city they belong to.
     *
     * @return array<string, array<int, array{name: string, delivery_delay: string, delivery_price: float, return_price: float, delivery_driver_price: float}>>
     */
    public static function sectorsByCity(): array
    {
        $sectors = [];

        foreach (self::rows() as $row) {
            $sectors[$row['city_name']][] = [
                'name' => $row['sector_name'],
                'delivery_delay' => $row['delivery_delay'],
                'delivery_price' => (float) $row['delivery_price'],
                'return_price' => (float) $row['return_price'],
                'delivery_driver_price' => (float) $row['delivery_driver_price'],
            ];
        }

        return $sectors;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function rows(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }

        $path = base_path(self::FILE);

        if (! is_readable($path)) {
            throw new RuntimeException('Coverage grid not found at '.self::FILE);
        }

        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle);

        if ($header !== self::COLUMNS) {
            fclose($handle);

            throw new RuntimeException(
                'Unexpected columns in '.self::FILE.', expected: '.implode(', ', self::COLUMNS)
            );
        }

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            // Skip the blank line a spreadsheet export leaves at the end.
            if ($line === [null] || $line === ['']) {
                continue;
            }

            $rows[] = array_combine(self::COLUMNS, $line);
        }

        fclose($handle);

        return self::$rows = $rows;
    }
}
