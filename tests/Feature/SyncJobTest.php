<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laraditz\Jaga\Events\PermissionsSynced;
use Laraditz\Jaga\Jobs\SyncPermissionsJob;
use Laraditz\Jaga\Models\Permission;

beforeEach(function () {
    Route::middleware('jaga')->get('/posts', fn () => '')->name('posts.index');
    Route::middleware('jaga')->post('/posts', fn () => '')->name('posts.store');
});

it('fires PermissionsSynced event when job is dispatched synchronously', function () {
    Event::fake([PermissionsSynced::class]);

    SyncPermissionsJob::dispatchSync();

    Event::assertDispatched(PermissionsSynced::class);
});

it('fires PermissionsSynced with correct new count on first sync', function () {
    Event::fake([PermissionsSynced::class]);

    SyncPermissionsJob::dispatchSync();

    Event::assertDispatched(PermissionsSynced::class, fn ($e) =>
        $e->newCount === 2 &&
        $e->updatedCount === 0 &&
        $e->deprecatedCount === 0 &&
        $e->collisions === []
    );
});

it('fires PermissionsSynced with correct updated count on re-sync', function () {
    Permission::create([
        'name'                => 'posts.index',
        'methods'             => ['GET'],
        'uri'                 => 'posts',
        'is_auto_description' => true,
    ]);

    Event::fake([PermissionsSynced::class]);

    SyncPermissionsJob::dispatchSync();

    Event::assertDispatched(PermissionsSynced::class, fn ($e) =>
        $e->newCount === 1 &&
        $e->updatedCount === 1
    );
});

it('fires PermissionsSynced with correct deprecated count for stale permissions', function () {
    Permission::create([
        'name'    => 'stale.route',
        'methods' => ['GET'],
        'uri'     => 'stale',
        'is_custom' => false,
        'is_auto_description' => true,
    ]);

    Event::fake([PermissionsSynced::class]);

    SyncPermissionsJob::dispatchSync();

    Event::assertDispatched(PermissionsSynced::class, fn ($e) =>
        $e->deprecatedCount === 1
    );
});

it('fires PermissionsSynced with collisions when custom permission name matches a route', function () {
    Permission::create([
        'name'      => 'posts.index',
        'methods'   => [],
        'uri'       => '',
        'is_custom' => true,
    ]);

    Event::fake([PermissionsSynced::class]);

    SyncPermissionsJob::dispatchSync();

    Event::assertDispatched(PermissionsSynced::class, fn ($e) =>
        in_array('posts.index', $e->collisions, true)
    );
});

it('creates permissions for named routes when dispatched as a job', function () {
    SyncPermissionsJob::dispatchSync();

    expect(Permission::where('name', 'posts.index')->exists())->toBeTrue();
    expect(Permission::where('name', 'posts.store')->exists())->toBeTrue();
});

it('implements ShouldQueue so it can be dispatched to a queue', function () {
    expect(SyncPermissionsJob::class)
        ->toImplement(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
