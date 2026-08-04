<?php

namespace App\Console\Commands;

use App\Enums\DriverInvoiceStatus;
use App\Enums\DriverTransactionStatus;
use App\Enums\DriverTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\OrderFailureReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PickupRequestStatus;
use App\Enums\ReturnInitiatedByRole;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\SupportObjectType;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Enums\TransferStatus;
use App\Enums\UserStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Exports the relational model and its rows as portable files:
 *
 *   storage/app/dataset/schema.json   tables, columns, keys, foreign keys, enums
 *   storage/app/dataset/dataset.json  every row, table by table
 *   storage/app/dataset/dataset.sql   ready-to-run INSERT script
 *
 * Sensitive user columns (password, tokens) are redacted unless
 * `--with-credentials` is passed.
 */
class DatasetExportCommand extends Command
{
    protected $signature = 'dataset:export
        {--dir= : Output directory (default storage/app/dataset)}
        {--format=all : json, sql or all}
        {--with-credentials : Export password hashes and tokens as-is}';

    protected $description = 'Export the relational schema and the full dataset as JSON and SQL';

    /**
     * Tables exported, in an order that can be replayed as-is.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'roles',
        'cities',
        'sectors',
        'users',
        'role_user',
        'driver_sector',
        'stores',
        'store_user',
        'orders',
        'order_status_histories',
        'order_change_histories',
        'pickup_requests',
        'pickup_status_histories',
        'transfers',
        'transfer_orders',
        'transfer_status_histories',
        'returns',
        'return_status_histories',
        'invoices',
        'invoice_orders',
        'invoice_logs',
        'driver_transactions',
        'driver_invoices',
        'driver_invoice_transactions',
        'driver_finance_logs',
        'support_tickets',
        'support_messages',
        'support_ticket_attachments',
    ];

    /** @var array<int, string> */
    private const REDACTED = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Business vocabularies stored as plain strings, surfaced in schema.json so a
     * consumer knows the allowed values of each status column.
     *
     * @var array<string, array<string, class-string>>
     */
    private const ENUM_COLUMNS = [
        'users' => ['status' => UserStatus::class],
        'orders' => [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'failure_reason' => OrderFailureReason::class,
            'invoice_status' => InvoiceStatus::class,
        ],
        'order_status_histories' => ['status' => OrderStatus::class],
        'pickup_requests' => ['status' => PickupRequestStatus::class],
        'pickup_status_histories' => ['old_status' => PickupRequestStatus::class, 'new_status' => PickupRequestStatus::class],
        'transfers' => ['status' => TransferStatus::class],
        'transfer_status_histories' => ['old_status' => TransferStatus::class, 'new_status' => TransferStatus::class],
        'returns' => [
            'status' => ReturnStatus::class,
            'reason' => ReturnReason::class,
            'initiated_by_role' => ReturnInitiatedByRole::class,
        ],
        'return_status_histories' => ['old_status' => ReturnStatus::class, 'new_status' => ReturnStatus::class],
        'invoices' => ['status' => InvoiceStatus::class],
        'invoice_orders' => ['order_status_at_invoice' => OrderStatus::class],
        'driver_transactions' => [
            'status' => DriverTransactionStatus::class,
            'transaction_type' => DriverTransactionType::class,
        ],
        'driver_invoices' => ['status' => DriverInvoiceStatus::class],
        'support_tickets' => [
            'status' => SupportTicketStatus::class,
            'category' => SupportTicketCategory::class,
            'object_type' => SupportObjectType::class,
        ],
    ];

    public function handle(): int
    {
        $directory = rtrim($this->option('dir') ?: storage_path('app/dataset'), '/');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->error("Unable to create {$directory}.");

            return self::FAILURE;
        }

        $format = (string) $this->option('format');
        $tables = array_values(array_filter(self::TABLES, fn (string $table) => DB::getSchemaBuilder()->hasTable($table)));

        $this->writeSchema($directory, $tables);

        if (in_array($format, ['json', 'all'], true)) {
            $this->writeJson($directory, $tables);
        }

        if (in_array($format, ['sql', 'all'], true)) {
            $this->writeSql($directory, $tables);
        }

        $this->newLine();
        $this->info("Export terminé dans {$directory}");

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Schema
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int, string>  $tables
     */
    private function writeSchema(string $directory, array $tables): void
    {
        $database = DB::getDatabaseName();
        $schema = [
            'database' => $database,
            'driver' => DB::getDriverName(),
            'generated_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $schema['tables'][$table] = [
                'columns' => $this->columns($database, $table),
                'primary_key' => $this->primaryKey($database, $table),
                'unique' => $this->uniqueKeys($database, $table),
                'foreign_keys' => $this->foreignKeys($database, $table),
                'enums' => $this->enums($table),
                'rows' => DB::table($table)->count(),
            ];
        }

        file_put_contents(
            $directory.'/schema.json',
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->line('  schema.json  : '.count($tables).' tables');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function columns(string $database, string $table): array
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->orderBy('ORDINAL_POSITION')
            ->get(['COLUMN_NAME', 'COLUMN_TYPE', 'IS_NULLABLE', 'COLUMN_DEFAULT', 'EXTRA', 'COLUMN_COMMENT'])
            ->map(fn ($column) => [
                'name' => $column->COLUMN_NAME,
                'type' => $column->COLUMN_TYPE,
                'nullable' => $column->IS_NULLABLE === 'YES',
                'default' => $column->COLUMN_DEFAULT,
                'extra' => $column->EXTRA ?: null,
                'comment' => $column->COLUMN_COMMENT ?: null,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function primaryKey(string $database, string $table): array
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', 'PRIMARY')
            ->orderBy('ORDINAL_POSITION')
            ->pluck('COLUMN_NAME')
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function uniqueKeys(string $database, string $table): array
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('NON_UNIQUE', 0)
            ->where('INDEX_NAME', '!=', 'PRIMARY')
            ->orderBy('INDEX_NAME')
            ->orderBy('SEQ_IN_INDEX')
            ->get(['INDEX_NAME', 'COLUMN_NAME'])
            ->groupBy('INDEX_NAME')
            ->map(fn ($columns) => $columns->pluck('COLUMN_NAME')->all())
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function foreignKeys(string $database, string $table): array
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE as kcu')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS as rc', function ($join) {
                $join->on('rc.CONSTRAINT_NAME', '=', 'kcu.CONSTRAINT_NAME')
                    ->on('rc.CONSTRAINT_SCHEMA', '=', 'kcu.TABLE_SCHEMA');
            })
            ->where('kcu.TABLE_SCHEMA', $database)
            ->where('kcu.TABLE_NAME', $table)
            ->whereNotNull('kcu.REFERENCED_TABLE_NAME')
            ->orderBy('kcu.CONSTRAINT_NAME')
            ->get([
                'kcu.COLUMN_NAME',
                'kcu.REFERENCED_TABLE_NAME',
                'kcu.REFERENCED_COLUMN_NAME',
                'rc.DELETE_RULE',
                'rc.UPDATE_RULE',
            ])
            ->map(fn ($key) => [
                'column' => $key->COLUMN_NAME,
                'references' => $key->REFERENCED_TABLE_NAME.'.'.$key->REFERENCED_COLUMN_NAME,
                'on_delete' => $key->DELETE_RULE,
                'on_update' => $key->UPDATE_RULE,
            ])
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function enums(string $table): array
    {
        $columns = self::ENUM_COLUMNS[$table] ?? [];

        return collect($columns)
            ->map(fn (string $enum) => array_map(
                static fn ($case) => $case->value,
                $enum::cases()
            ))
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Data
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int, string>  $tables
     */
    private function writeJson(string $directory, array $tables): void
    {
        $path = $directory.'/dataset.json';
        $handle = fopen($path, 'w');
        fwrite($handle, "{\n");

        $total = 0;

        foreach ($tables as $index => $table) {
            fwrite($handle, '  '.json_encode($table).": [\n");

            $first = true;
            $count = 0;

            DB::table($table)->orderBy($this->orderColumn($table))->chunk(500, function ($rows) use ($handle, $table, &$first, &$count) {
                foreach ($rows as $row) {
                    fwrite($handle, ($first ? '' : ",\n").'    '.json_encode(
                        $this->sanitize($table, (array) $row),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ));
                    $first = false;
                    $count++;
                }
            });

            fwrite($handle, "\n  ]".($index === count($tables) - 1 ? '' : ',')."\n");
            $total += $count;
        }

        fwrite($handle, "}\n");
        fclose($handle);

        $this->line('  dataset.json : '.number_format($total).' lignes');
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function writeSql(string $directory, array $tables): void
    {
        $path = $directory.'/dataset.sql';
        $handle = fopen($path, 'w');
        $pdo = DB::getPdo();

        fwrite($handle, "-- OWL Delivery — jeu de données logistique Maroc\n");
        fwrite($handle, '-- Base source : '.DB::getDatabaseName()."\n");
        fwrite($handle, '-- Généré le : '.now()->toDateTimeString()."\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET AUTOCOMMIT=0;\nSTART TRANSACTION;\n\n");

        $total = 0;

        foreach ($tables as $table) {
            $count = DB::table($table)->count();

            if ($count === 0) {
                continue;
            }

            fwrite($handle, "-- {$table} ({$count} lignes)\n");

            $buffer = [];
            $columns = null;

            DB::table($table)->orderBy($this->orderColumn($table))->chunk(500, function ($rows) use ($handle, $table, $pdo, &$buffer, &$columns) {
                foreach ($rows as $row) {
                    $values = $this->sanitize($table, (array) $row);
                    $columns ??= array_keys($values);

                    $buffer[] = '('.implode(', ', array_map(
                        fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                        $values
                    )).')';

                    if (count($buffer) >= 200) {
                        $this->flushInsert($handle, $table, $columns, $buffer);
                    }
                }
            });

            if ($buffer !== []) {
                $this->flushInsert($handle, $table, $columns ?? [], $buffer);
            }

            fwrite($handle, "\n");
            $total += $count;
        }

        fwrite($handle, "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        $this->line('  dataset.sql  : '.number_format($total).' lignes');
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $buffer
     */
    private function flushInsert($handle, string $table, array $columns, array &$buffer): void
    {
        $quoted = implode(', ', array_map(static fn (string $column) => "`{$column}`", $columns));

        fwrite($handle, "INSERT INTO `{$table}` ({$quoted}) VALUES\n".implode(",\n", $buffer).";\n");

        $buffer = [];
    }

    /**
     * Hide credentials unless the caller explicitly asks for them.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function sanitize(string $table, array $row): array
    {
        if ($table !== 'users' || $this->option('with-credentials')) {
            return $row;
        }

        foreach (self::REDACTED as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = $column === 'password' ? '__REDACTED__' : null;
            }
        }

        return $row;
    }

    /**
     * Pivot tables have no id, so fall back to their first column.
     */
    private function orderColumn(string $table): string
    {
        $columns = DB::getSchemaBuilder()->getColumnListing($table);

        return in_array('id', $columns, true) ? 'id' : ($columns[0] ?? 'id');
    }
}
