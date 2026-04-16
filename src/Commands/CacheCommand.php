<?php

namespace Laraditz\Jaga\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laraditz\Jaga\Models\Permission;

class CacheCommand extends Command
{
    protected $signature = 'jaga:cache';
    protected $description = 'Pre-warm the jaga permissions cache';

    public function handle(): int
    {
        $permissions = Permission::all();
        Cache::put(
            config('jaga.cache.key_prefix', 'jaga').'.permissions',
            $permissions,
            config('jaga.cache.ttl', 3600)
        );
        $this->info("Cached {$permissions->count()} permission(s).");

        $levels = DB::table(config('jaga.tables.permissions'))
            ->whereNull('deleted_at')
            ->pluck('access_level', 'name')
            ->toArray();

        Cache::put(
            config('jaga.cache.key_prefix', 'jaga').'.access_levels',
            $levels,
            config('jaga.cache.ttl', 3600)
        );
        $this->info('Cached access levels for '.count($levels).' permission(s).');

        return self::SUCCESS;
    }
}
