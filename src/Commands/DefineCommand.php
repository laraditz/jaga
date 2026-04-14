<?php

namespace Laraditz\Jaga\Commands;

use Illuminate\Console\Command;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Support\CacheManager;

class DefineCommand extends Command
{
    protected $signature = 'jaga:define
                            {name : The permission name (e.g. export-reports)}
                            {--description= : Human-readable description}
                            {--group= : Optional category for admin UI grouping}';

    protected $description = 'Create or update a custom permission not tied to any route';

    public function handle(CacheManager $cache): int
    {
        $name = $this->argument('name');

        $existing = Permission::withTrashed()->where('name', $name)->first();

        if ($existing && ! $existing->is_custom) {
            $this->error("Permission '{$name}' already exists as a route-based permission and cannot be overwritten.");
            return self::FAILURE;
        }

        if ($existing) {
            // Update existing custom permission (restoring if soft-deleted)
            $update = ['deleted_at' => null];

            if ($this->option('description') !== null) {
                $update['description']         = $this->option('description');
                $update['is_auto_description'] = false;
            }

            if ($this->option('group') !== null) {
                $update['group'] = $this->option('group');
            }

            $existing->fill($update)->save();
        } else {
            // Create new custom permission
            Permission::create([
                'name'                => $name,
                'methods'             => [],
                'uri'                 => '',
                'description'         => $this->option('description') ?? $name,
                'is_auto_description' => false,
                'is_custom'           => true,
                'group'               => $this->option('group'),
            ]);
        }

        $cache->flushAll();
        $this->info("Custom permission '{$name}' defined.");

        return self::SUCCESS;
    }
}
