<?php

namespace Laraditz\Jaga\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Support\CacheManager;
use Laraditz\Jaga\Support\DescriptionGenerator;

class SyncCommand extends Command
{
    protected $signature = 'jaga:sync';
    protected $description = 'Sync named routes to the permissions table';

    public function handle(CacheManager $cache): int
    {
        $routes      = Route::getRoutes();
        $syncedNames = [];
        $newCount    = 0;
        $updatedCount = 0;

        foreach ($routes as $route) {
            $name = $route->getName();

            if (! $name || $this->isExcluded($name, $route->uri())) {
                continue;
            }

            $syncedNames[] = $name;
            $methods = $route->methods();
            $uri     = $route->uri();

            $existing = Permission::withTrashed()->where('name', $name)->first();

            if (! $existing) {
                Permission::create([
                    'name'                => $name,
                    'methods'             => $methods,
                    'uri'                 => $uri,
                    'description'         => DescriptionGenerator::generate($name),
                    'is_auto_description' => true,
                ]);
                $newCount++;
            } else {
                $update = ['methods' => $methods, 'uri' => $uri, 'deleted_at' => null];

                if ($existing->is_auto_description) {
                    $update['description'] = DescriptionGenerator::generate($name);
                }

                $existing->fill($update)->save();
                $updatedCount++;
            }
        }

        // Soft-delete stale permissions
        $deprecated = Permission::whereNotIn('name', $syncedNames)->get();
        foreach ($deprecated as $perm) {
            $perm->delete();
        }

        $this->table(
            ['New', 'Updated', 'Deprecated'],
            [[$newCount, $updatedCount, $deprecated->count()]]
        );

        $cache->flushAll();
        $this->info('Permissions synced and caches cleared.');

        return self::SUCCESS;
    }

    private function isExcluded(string $name, string $uri): bool
    {
        foreach (config('jaga.sync.exclude_name_prefixes', []) as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }
        foreach (config('jaga.sync.exclude_uri_prefixes', []) as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
