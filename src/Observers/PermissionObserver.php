<?php

namespace Laraditz\Jaga\Observers;

use Illuminate\Support\Facades\DB;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Support\CacheManager;

class PermissionObserver
{
    public function __construct(private CacheManager $cache) {}

    public function saved(Permission $permission): void
    {
        $this->cache->flushAll();
    }

    public function deleting(Permission $permission): void
    {
        DB::table(config('jaga.tables.role_permission'))
            ->where('permission_id', $permission->id)
            ->delete();

        DB::table(config('jaga.tables.model_permission'))
            ->where('permission_id', $permission->id)
            ->delete();
    }

    public function deleted(Permission $permission): void
    {
        $this->cache->flushAll();
    }

    public function restored(Permission $permission): void
    {
        $this->cache->flushAll();
    }

    public function forceDeleted(Permission $permission): void
    {
        $this->cache->flushAll();
    }
}
