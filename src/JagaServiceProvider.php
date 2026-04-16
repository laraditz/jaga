<?php

namespace Laraditz\Jaga;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laraditz\Jaga\Commands\CacheCommand;
use Laraditz\Jaga\Commands\CleanCommand;
use Laraditz\Jaga\Commands\ClearCommand;
use Laraditz\Jaga\Commands\DefineCommand;
use Laraditz\Jaga\Commands\SeederCommand;
use Laraditz\Jaga\Commands\SyncCommand;
use Laraditz\Jaga\Middleware\JagaMiddleware;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Observers\PermissionObserver;

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
        Permission::observe(PermissionObserver::class);

        $this->publishes([
            __DIR__ . '/../config/jaga.php' => config_path('jaga.php'),
        ], 'jaga-config');

        $this->publishMigrations();

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

    protected function publishMigrations()
    {
        $databasePath = __DIR__ . '/../database/migrations/';
        $migrationPath = database_path('migrations/');

        $files = array_diff(scandir($databasePath), array('.', '..'));
        $date = date('Y_m_d');
        $time = date('His');

        $migrationFiles = collect($files)
            ->mapWithKeys(function (string $file) use ($databasePath, $migrationPath, $date, &$time) {
                $filename = Str::replace(Str::substr($file, 0, 17), '', $file);

                $found = glob($migrationPath . '*' . $filename);
                $time = date("His", strtotime($time) + 1); // ensure in order
    
                return !!count($found) === true ? []
                    : [
                        $databasePath . $file => $migrationPath . $date . '_' . $time . $filename,
                    ];
            });

        if ($migrationFiles->isNotEmpty()) {
            $this->publishes($migrationFiles->toArray(), 'jaga-migrations');
        }
    }
}
