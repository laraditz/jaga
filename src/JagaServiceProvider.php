<?php

namespace Laraditz\Jaga;

use Illuminate\Support\ServiceProvider;
use Laraditz\Jaga\Commands\CacheCommand;
use Laraditz\Jaga\Commands\CleanCommand;
use Laraditz\Jaga\Commands\ClearCommand;
use Laraditz\Jaga\Commands\SyncCommand;
use Laraditz\Jaga\Middleware\JagaMiddleware;

class JagaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jaga.php', 'jaga');
        $this->app->bind('jaga', fn () => new class {});
        $this->app->singleton(\Laraditz\Jaga\Support\CacheManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/jaga.php' => config_path('jaga.php'),
        ], 'jaga-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'jaga-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app['router']->aliasMiddleware('rbac', JagaMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCommand::class,
                CleanCommand::class,
                CacheCommand::class,
                ClearCommand::class,
            ]);
        }
    }
}
