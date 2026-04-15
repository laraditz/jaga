<?php

namespace Laraditz\Jaga\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laraditz\Jaga\Middleware\JagaMiddleware;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Support\CacheManager;
use Laraditz\Jaga\Support\DescriptionGenerator;

class SyncCommand extends Command
{
    protected $signature = 'jaga:sync';
    protected $description = 'Sync named routes to the permissions table';

    public function handle(CacheManager $cache): int
    {
        $routes = Route::getRoutes();
        $syncedNames = [];
        $newCount = 0;
        $updatedCount = 0;
        $collisions = [];

        // Resolve all aliases that point to JagaMiddleware (e.g. 'jaga')
        $jagaAliases = array_keys(array_filter(
            app('router')->getMiddleware(),
            fn($class) => $class === JagaMiddleware::class
        ));

        foreach ($routes as $route) {
            $name = $route->getName();

            if (!$name || $this->isExcluded($name, $route->uri())) {
                continue;
            }

            // Only sync routes protected by jaga middleware
            if (!$this->hasJagaMiddleware($route, $jagaAliases)) {
                continue;
            }

            $syncedNames[] = $name;
            $methods = $route->methods();
            $uri = $route->uri();

            $existing = Permission::withTrashed()->where('name', $name)->first();

            if (!$existing) {
                $overrides = $this->configOverridesFor($name);
                $description = $overrides['description'] ?? DescriptionGenerator::generate($name);
                $isAutoDesc = !isset($overrides['description']);
                $group = $overrides['group'] ?? DescriptionGenerator::group($name);

                Permission::create([
                    'name' => $name,
                    'methods' => $methods,
                    'uri' => $uri,
                    'description' => $description,
                    'is_auto_description' => $isAutoDesc,
                    'is_public' => !$this->hasAuthMiddleware($route),
                    'group' => $group,
                ]);
                $newCount++;
            } else {
                // Skip updating custom permissions — record collision for warning
                if ($existing->is_custom) {
                    $collisions[] = $name;
                    continue;
                }

                $overrides = $this->configOverridesFor($name);
                $update = ['methods' => $methods, 'uri' => $uri, 'deleted_at' => null];

                if (isset($overrides['description'])) {
                    $update['description'] = $overrides['description'];
                    $update['is_auto_description'] = false;
                } elseif ($existing->is_auto_description) {
                    $update['description'] = DescriptionGenerator::generate($name);
                }

                if (isset($overrides['group'])) {
                    $update['group'] = $overrides['group'];
                } elseif ($existing->group === null) {
                    $update['group'] = DescriptionGenerator::group($name);
                }

                $existing->fill($update)->save();
                $updatedCount++;
            }
        }

        // Soft-delete stale non-custom permissions
        $deprecated = Permission::whereNotIn('name', $syncedNames)
            ->where('is_custom', false)
            ->get();
        foreach ($deprecated as $perm) {
            $perm->delete();
        }

        $this->table(
            ['New', 'Updated', 'Deprecated'],
            [[$newCount, $updatedCount, $deprecated->count()]]
        );

        // Emit collision warnings after the loop
        foreach ($collisions as $collision) {
            $this->warn("Custom permission \"{$collision}\" conflicts with a route of the same name. The custom permission was not modified.");
        }

        $cache->flushAll();
        $this->info('Permissions synced and caches cleared.');

        return self::SUCCESS;
    }

    private function configOverridesFor(string $name): array
    {
        $permissions = config('jaga.permissions', []);

        return Arr::get($permissions, $name, []);
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

    private function hasJagaMiddleware(\Illuminate\Routing\Route $route, array $jagaAliases): bool
    {
        return collect($route->gatherMiddleware())
            ->map(fn($m) => Str::before($m, ':'))
            ->intersect([...$jagaAliases, JagaMiddleware::class])
            ->isNotEmpty();
    }

    private function hasAuthMiddleware(\Illuminate\Routing\Route $route): bool
    {
        return collect($route->gatherMiddleware())
            ->map(fn($m) => Str::before($m, ':'))
            ->contains('auth');
    }
}
