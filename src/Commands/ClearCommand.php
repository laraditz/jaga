<?php

namespace Laraditz\Jaga\Commands;

use Illuminate\Console\Command;
use Laraditz\Jaga\Support\CacheManager;

class ClearCommand extends Command
{
    protected $signature = 'jaga:clear';
    protected $description = 'Clear all jaga caches';

    public function handle(CacheManager $cache): int
    {
        $cache->flushAll();
        $this->info('All jaga caches cleared.');
        return self::SUCCESS;
    }
}
