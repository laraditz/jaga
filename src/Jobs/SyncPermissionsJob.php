<?php

namespace Laraditz\Jaga\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laraditz\Jaga\Enums\AccessLevel;
use Laraditz\Jaga\Events\PermissionsSynced;
use Laraditz\Jaga\Middleware\JagaMiddleware;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Support\CacheManager;
use Laraditz\Jaga\Support\DescriptionGenerator;

class SyncPermissionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CacheManager $cache): void
    {
        $routes = Route::getRoutes();
        $syncedNames = [];
        $newCount = 0;
        $updatedCount = 0;
        $collisions = [];

        $jagaAliases = array_keys(array_filter(
            app('router')->getMiddleware(),
            fn ($class) => $class === JagaMiddleware::class
        ));

        foreach ($routes as $route) {
            $name = $route->getName();

            if (!$name || $this->isExcluded($name, $route->uri())) {
                continue;
            }

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
                $accessLevel = $overrides['access_level']
                    ?? ($this->hasAuthMiddleware($route) ? AccessLevel::Restricted->value : AccessLevel::Public->value);

                Permission::create([
                    'name' => $name,
                    'methods' => $methods,
                    'uri' => $uri,
                    'description' => $description,
                    'is_auto_description' => $isAutoDesc,
                    'access_level' => $accessLevel,
                    'group' => $group,
                ]);
                $newCount++;
            } else {
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

                if (isset($overrides['access_level'])) {
                    $update['access_level'] = $overrides['access_level'];
                }

                $existing->fill($update)->save();
                $updatedCount++;
            }
        }

        $deprecated = Permission::whereNotIn('name', $syncedNames)
            ->where('is_custom', false)
            ->get();

        $deprecatedCount = $deprecated->count();

        foreach ($deprecated as $perm) {
            $perm->delete();
        }

        $cache->flushAll();

        Event::dispatch(new PermissionsSynced($newCount, $updatedCount, $deprecatedCount, $collisions));
    }

    private function configOverridesFor(string $name): array
    {
        return Arr::get(config('jaga.permissions', []), $name, []);
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
            ->map(fn ($m) => Str::before($m, ':'))
            ->intersect([...$jagaAliases, JagaMiddleware::class])
            ->isNotEmpty();
    }

    private function hasAuthMiddleware(\Illuminate\Routing\Route $route): bool
    {
        return collect($route->gatherMiddleware())
            ->map(fn ($m) => Str::before($m, ':'))
            ->contains('auth');
    }
}
