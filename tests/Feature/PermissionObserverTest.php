<?php

use Illuminate\Support\Facades\DB;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;

// ── Cache invalidation ────────────────────────────────────────────────────────

it('flushes cache when a permission is created', function () {
    seedJagaCache();

    Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);

    assertJagaCacheFlushed();
});

it('flushes cache when a permission is updated', function () {
    $perm = Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
    seedJagaCache();

    $perm->update(['description' => 'Updated']);

    assertJagaCacheFlushed();
});

it('flushes cache when a permission is soft-deleted', function () {
    $perm = Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
    seedJagaCache();

    $perm->delete();

    assertJagaCacheFlushed();
});

it('flushes cache when a permission is restored', function () {
    $perm = Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
    $perm->delete();
    seedJagaCache();

    $perm->restore();

    assertJagaCacheFlushed();
});

it('flushes cache when a permission is force-deleted', function () {
    $perm = Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
    seedJagaCache();

    $perm->forceDelete();

    assertJagaCacheFlushed();
});

// ── Pivot cleanup ─────────────────────────────────────────────────────────────

it('deletes pivot rows when a permission is soft-deleted', function () {
    $perm = Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'guard_name' => 'web']);
    DB::table(config('jaga.tables.role_permission'))->insert([
        'role_id'       => $role->id,
        'permission_id' => $perm->id,
        'created_at'    => now(),
    ]);

    $perm->delete();

    expect(
        DB::table(config('jaga.tables.role_permission'))
            ->where('permission_id', $perm->id)
            ->exists()
    )->toBeFalse();
});

it('deletes pivot rows when a permission is force-deleted', function () {
    $perm = Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'guard_name' => 'web']);
    DB::table(config('jaga.tables.role_permission'))->insert([
        'role_id'       => $role->id,
        'permission_id' => $perm->id,
        'created_at'    => now(),
    ]);

    $perm->forceDelete();

    expect(
        DB::table(config('jaga.tables.role_permission'))
            ->where('permission_id', $perm->id)
            ->exists()
    )->toBeFalse();
});
