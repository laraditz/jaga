<?php

namespace Laraditz\Jaga;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laraditz\Jaga\Commands\CacheCommand;
use Laraditz\Jaga\Commands\CleanCommand;
use Laraditz\Jaga\Commands\ClearCommand;
use Laraditz\Jaga\Commands\DefineCommand;
use Laraditz\Jaga\Commands\SeederCommand;
use Laraditz\Jaga\Commands\SyncCommand;
use Laraditz\Jaga\Middleware\JagaMiddleware;

class JagaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/jaga.php', 'jaga');
        $this->app->singleton('jaga', fn() => new \Laraditz\Jaga\Jaga());
        $this->app->alias('jaga', \Laraditz\Jaga\Jaga::class);
        $this->app->singleton(\Laraditz\Jaga\Support\CacheManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/jaga.php' => config_path('jaga.php'),
        ], 'jaga-config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'jaga-migrations');

        $this->app['router']->aliasMiddleware('jaga', JagaMiddleware::class);

        Gate::before(function ($user, string $ability) {
            if (!method_exists($user, 'hasPermission')) {
                return null;
            }

            return $user->hasPermission($ability) ?: null;
        });

        Blade::if('role', function (string|int|array $role, $record = null): bool {
            $subject = $record ?? Auth::user();
            return $subject && method_exists($subject, 'hasRole') && $subject->hasRole($role);
        });

        Blade::if('permission', function (string $permission, $record = null): bool {
            $subject = $record ?? Auth::user();
            return $subject && method_exists($subject, 'hasPermission') && $subject->hasPermission($permission);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCommand::class,
                CleanCommand::class,
                CacheCommand::class,
                ClearCommand::class,
                DefineCommand::class,
                SeederCommand::class,
            ]);
        }
    }
}
