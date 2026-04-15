<?php

namespace Laraditz\Jaga\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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

        $public = Permission::where('is_public', true)
            ->whereNull('deleted_at')
            ->pluck('name')
            ->toArray();

        Cache::put(
            config('jaga.cache.key_prefix', 'jaga').'.public_routes',
            $public,
            config('jaga.cache.ttl', 3600)
        );
        $this->info('Cached '.count($public).' public route(s).');

        return self::SUCCESS;
    }
}
