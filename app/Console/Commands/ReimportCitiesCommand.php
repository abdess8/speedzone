<?php

namespace App\Console\Commands;

use App\Models\City;
use Database\Seeders\CitySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the delivery network from database/data/cities-sectors.csv.
 *
 * Cities and sectors sit under the whole operational history: orders and
 * transfers point at them through non-nullable, restricted foreign keys, so a
 * clean re-import is only possible once that history is gone. This command owns
 * that trade-off explicitly rather than leaving a half-purged database behind.
 */
class ReimportCitiesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cities:reimport
        {--force : Skip the confirmation prompt}';

    /**
     * @var string
     */
    protected $description = 'Delete every city and sector, along with the operational data that depends on them, and re-import the coverage grid.';

    /**
     * Tables emptied before the network is rebuilt, in an order that respects
     * the foreign keys. Children left to MySQL: deleting a parent here cascades
     * to its own history, items and pivot rows.
     *
     * @var array<int, string>
     */
    private const PURGED = [
        // Driver payouts, snapshotted from sector rates.
        'driver_invoices',
        'driver_transactions',
        'driver_finance_logs',
        // Must precede orders: transfer_orders restricts deleting an order.
        'transfers',
        'pickup_requests',
        // Cascades to order items, status histories and returns.
        'orders',
        'invoices',
        // Stock movements, tied to a destination city.
        'stock_inventory_counts',
        'stock_adjustments',
        'stock_receptions',
        // Assignment pivots.
        'driver_sector',
        'partner_city',
        'partner_sector',
    ];

    public function handle(): int
    {
        $counts = $this->countsToPurge();

        $this->components->warn('This will permanently delete:');
        $this->table(
            ['Table', 'Rows'],
            collect($counts)->map(fn (int $rows, string $table) => [$table, $rows])->values()->all()
        );

        if (! $this->option('force') && ! $this->confirm('Delete this data and re-import the coverage grid?')) {
            $this->components->info('Aborted, nothing was changed.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            foreach (self::PURGED as $table) {
                DB::table($table)->delete();
            }

            // Not soft deletes: the rows must actually leave, or the unique
            // index on the city code would reject the re-imported grid.
            DB::table('sectors')->delete();
            DB::table('cities')->delete();
        });

        $this->components->info('Dependent data purged.');

        $this->callSilent('db:seed', ['--class' => CitySeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => SectorSeeder::class, '--force' => true]);

        City::flushOptionsCache();

        $this->components->info(sprintf(
            'Imported %d cities and %d sectors.',
            DB::table('cities')->count(),
            DB::table('sectors')->count(),
        ));

        // Deliberately withheld in production: DatabaseSeeder creates demo
        // accounts on a shared password and thousands of fake orders.
        if (! app()->isProduction()) {
            $this->components->info('Run `php artisan db:seed` to regenerate demo orders on the new network.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function countsToPurge(): array
    {
        $counts = [];

        foreach ([...self::PURGED, 'sectors', 'cities'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
