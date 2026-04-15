<?php

use Illuminate\Support\Facades\DB;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;

it('can create a role', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'guard_name' => 'web']);
    expect($role->slug)->toBe('editor');
});

it('can create a permission', function () {
    $perm = Permission::create([
        'name' => 'posts.index',
        'methods'    => ['GET'],
        'uri'        => 'posts',
        'description' => 'List all posts',
        'is_auto_description' => true,
    ]);
    expect($perm->name)->toBe('posts.index');
});

it('soft deletes a permission', function () {
    $perm = Permission::create([
        'name' => 'posts.store',
        'methods'    => ['POST'],
        'uri'        => 'posts',
    ]);
    $perm->delete();
    expect(Permission::find($perm->id))->toBeNull();
    expect(Permission::withTrashed()->find($perm->id))->not->toBeNull();
});

it('soft deletes role_permission rows when permission is soft-deleted', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
    $perm = Permission::create(['name' => 'posts.update', 'methods' => ['PUT'], 'uri' => 'posts/{post}']);
    DB::table('role_permission')->insert(['role_id' => $role->id, 'permission_id' => $perm->id, 'wildcard' => null]);

    $perm->delete();

    expect(DB::table('role_permission')->where('permission_id', $perm->id)->count())->toBe(0);
});
