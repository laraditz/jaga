<?php

namespace Laraditz\Jaga\Commands;

use Illuminate\Console\Command;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Support\CacheManager;

class CleanCommand extends Command
{
    protected $signature = 'jaga:clean';
    protected $description = 'Force-delete soft-deleted permissions and flush all jaga caches';

    public function handle(CacheManager $cache): int
    {
        $count = Permission::onlyTrashed()->where('is_custom', false)->count();
        Permission::onlyTrashed()->where('is_custom', false)->each(fn (Permission $p) => $p->forceDelete());
        $this->info("Force-deleted {$count} deprecated permission(s).");
        $cache->flushAll();
        $this->info('All jaga caches cleared.');
        return self::SUCCESS;
    }
}
