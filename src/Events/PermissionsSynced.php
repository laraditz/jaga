<?php

namespace Laraditz\Jaga\Events;

class PermissionsSynced
{
    public function __construct(
        public readonly int $newCount,
        public readonly int $updatedCount,
        public readonly int $deprecatedCount,
        public readonly array $collisions,
    ) {}
}
