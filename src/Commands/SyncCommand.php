<?php

namespace Laraditz\Jaga\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Laraditz\Jaga\Events\PermissionsSynced;
use Laraditz\Jaga\Jobs\SyncPermissionsJob;

class SyncCommand extends Command
{
    protected $signature = 'jaga:sync';
    protected $description = 'Sync named routes to the permissions table';

    public function handle(): int
    {
        Event::listen(PermissionsSynced::class, function (PermissionsSynced $event) {
            $this->table(
                ['New', 'Updated', 'Deprecated'],
                [[$event->newCount, $event->updatedCount, $event->deprecatedCount]]
            );

            foreach ($event->collisions as $collision) {
                $this->warn("Custom permission \"{$collision}\" conflicts with a route of the same name. The custom permission was not modified.");
            }

            $this->info('Permissions synced and caches cleared.');
        });

        SyncPermissionsJob::dispatchSync();

        return self::SUCCESS;
    }
}
