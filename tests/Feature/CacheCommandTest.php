<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laraditz\Jaga\Models\Permission;

it('jaga:cache warms the permissions cache', function () {
    Route::middleware('jaga')->get('/posts', fn () => '')->name('posts.index');
    $this->artisan('jaga:sync')->assertSuccessful();
    Cache::flush();

    $this->artisan('jaga:cache')->assertSuccessful();
    $key = config('jaga.cache.key_prefix', 'jaga').'.permissions';
    expect(Cache::get($key))->not->toBeNull();
});

it('jaga:clear flushes all jaga caches', function () {
    Cache::put('jaga.permissions', 'data', 60);
    $this->artisan('jaga:clear')->assertSuccessful();
    expect(Cache::get('jaga.permissions'))->toBeNull();
});

it('jaga:cache warms the public routes cache', function () {
    Permission::create([
        'name'      => 'home',
        'methods'   => ['GET'],
        'uri'       => '/',
        'is_public' => true,
    ]);
    Cache::flush();

    $this->artisan('jaga:cache')->assertSuccessful();

    $key = config('jaga.cache.key_prefix', 'jaga').'.public_routes';
    expect(Cache::get($key))->toBe(['home']);
});
