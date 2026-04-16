<?php

namespace Laraditz\Jaga\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeederCommand extends Command
{
    protected $signature = 'jaga:seeder {--force : Overwrite the seeder file if it already exists}';
    protected $description = 'Generate a seeder class from the current roles, permissions, and role-permission data';

    public function handle(): int
    {
        $path = config('jaga.seeder.path') ?? database_path('seeders/JagaSeeder.php');

        if (file_exists($path) && ! $this->option('force')) {
            $this->error("Seeder file already exists at [{$path}]. Use --force to overwrite.");
            return self::FAILURE;
        }

        $roles     = DB::table(config('jaga.tables.roles'))->get()->map(fn ($r) => (array) $r)->all();
        $perms     = DB::table(config('jaga.tables.permissions'))->get()->map(fn ($r) => (array) $r)->all();
        $rolePerms = DB::table(config('jaga.tables.role_permission'))->get()->map(fn ($r) => (array) $r)->all();

        $contents = $this->renderSeeder($roles, $perms, $rolePerms);

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $contents);

        $this->info("Seeder written to [{$path}].");

        return self::SUCCESS;
    }

    private function renderSeeder(array $roles, array $perms, array $rolePerms): string
    {
        $rolesPhp     = $this->renderInsert('roles', $roles);
        $permsPhp     = $this->renderInsert('permissions', $perms);
        $rolePermsPhp = $this->renderInsert('role_permission', $rolePerms);

        $truncate = implode("\n", [
            "        DB::table(config('jaga.tables.role_permission'))->truncate();",
            "        DB::table(config('jaga.tables.permissions'))->truncate();",
            "        DB::table(config('jaga.tables.roles'))->truncate();",
        ]);

        return "<?php\n\nnamespace Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\nuse Illuminate\\Support\\Facades\\DB;\n\nclass JagaSeeder extends Seeder\n{\n    public function run(): void\n    {\n{$truncate}\n\n{$rolesPhp}{$permsPhp}{$rolePermsPhp}    }\n}\n";
    }

    private function renderInsert(string $tableKey, array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $rowsPhp = array_map(function (array $row): string {
            $pairs = array_map(
                fn ($col, $val) => '            ' . var_export($col, true) . ' => ' . var_export($val, true),
                array_keys($row),
                array_values($row)
            );
            return "        [\n" . implode(",\n", $pairs) . ",\n        ]";
        }, $rows);

        $rowsStr = implode(",\n", $rowsPhp);

        return "        DB::table(config('jaga.tables.{$tableKey}'))->insert([\n{$rowsStr},\n        ]);\n\n";
    }
}
