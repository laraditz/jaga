<?php

use Illuminate\Support\Facades\Cache;
use Laraditz\Jaga\Support\CacheManager;

beforeEach(fn () => Cache::flush());

it('stores and retrieves a user permission cache', function () {
    $manager = new CacheManager;
    $manager->putUserPermissions('App\\Models\\User', 1, ['posts.index']);
    expect($manager->getUserPermissions('App\\Models\\User', 1))->toBe(['posts.index']);
});

it('flushes a specific user cache', function () {
    $manager = new CacheManager;
    $manager->putUserPermissions('App\\Models\\User', 1, ['posts.index']);
    $manager->flushUser('App\\Models\\User', 1);
    expect($manager->getUserPermissions('App\\Models\\User', 1))->toBeNull();
});

it('flushes all jaga caches', function () {
    $manager = new CacheManager;
    $manager->putUserPermissions('App\\Models\\User', 1, ['posts.index']);
    $manager->putUserPermissions('App\\Models\\User', 2, ['posts.store']);
    $manager->flushAll();
    expect($manager->getUserPermissions('App\\Models\\User', 1))->toBeNull();
    expect($manager->getUserPermissions('App\\Models\\User', 2))->toBeNull();
});

it('respects cache disabled config', function () {
    config(['jaga.cache.enabled' => false]);
    $manager = new CacheManager;
    $manager->putUserPermissions('App\\Models\\User', 1, ['posts.index']);
    expect($manager->getUserPermissions('App\\Models\\User', 1))->toBeNull();
});
