<?php

namespace App\Console\Commands;

use App\Support\RolePermissionMatrix;
use Illuminate\Console\Command;

class ExportRbacMatrixCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rbac:matrix
        {--output= : Write the JSON to this path instead of stdout}
        {--table : Render a human-readable table instead of JSON}';

    /**
     * @var string
     */
    protected $description = 'Export the role/permission matrix as JSON, generated from PermissionCatalog and RolePermissionMatrix.';

    public function handle(): int
    {
        if ($this->option('table')) {
            $this->renderTable();

            return self::SUCCESS;
        }

        $json = json_encode(
            RolePermissionMatrix::export(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            $this->error('Unable to encode the permission matrix.');

            return self::FAILURE;
        }

        $output = $this->option('output');

        if ($output) {
            $directory = dirname($output);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($output, $json.PHP_EOL);
            $this->info("Permission matrix written to {$output}");

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }

    private function renderTable(): void
    {
        $grants = RolePermissionMatrix::resolvedGrants();
        $roles = array_keys($grants);

        $permissions = collect($grants)->flatten()->unique()->sort()->values();

        $this->table(
            ['Permission', ...$roles],
            $permissions->map(fn (string $permission) => [
                $permission,
                ...array_map(
                    fn (string $role) => in_array($permission, $grants[$role], true) ? 'yes' : '-',
                    $roles
                ),
            ])->all()
        );
    }
}
