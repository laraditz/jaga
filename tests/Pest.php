<?php

use Illuminate\Support\Facades\Cache;

uses(Laraditz\Jaga\Tests\TestCase::class)->in('Feature', 'Unit');

function seedJagaCache(): void
{
    $prefix = config('jaga.cache.key_prefix', 'jaga');
    Cache::put("{$prefix}.access_levels", ['posts.index' => 'restricted'], 60);
    Cache::put("{$prefix}.permissions", collect([]), 60);
}

function assertJagaCacheFlushed(): void
{
    $prefix = config('jaga.cache.key_prefix', 'jaga');
    expect(Cache::get("{$prefix}.access_levels"))->toBeNull()
        ->and(Cache::get("{$prefix}.permissions"))->toBeNull();
}
