<?php

use Illuminate\Support\Facades\DB;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;
use Laraditz\Jaga\Tests\TestUser;

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'x']);
    $this->role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'guard_name' => 'web']);
    $this->perm = Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
});

it('assigns a role by slug', function () {
    $this->user->assignRole('editor');
    expect($this->user->roles()->count())->toBe(1);
});

it('assigns multiple roles at once', function () {
    $admin = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
    $this->user->assignRole(['editor', 'admin']);
    expect($this->user->roles()->count())->toBe(2);
});

it('removes multiple roles at once', function () {
    $admin = Role::create(['name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web']);
    $this->user->assignRole(['editor', 'admin']);
    $this->user->removeRole(['editor', 'admin']);
    expect($this->user->roles()->count())->toBe(0);
});

it('assigns a role by model', function () {
    $this->user->assignRole($this->role);
    expect($this->user->roles()->first()->slug)->toBe('editor');
});

it('removes a role', function () {
    $this->user->assignRole('editor');
    $this->user->removeRole('editor');
    expect($this->user->roles()->count())->toBe(0);
});

it('grants a direct permission by route name', function () {
    $this->user->grantPermission('posts.index');
    expect($this->user->permissions()->count())->toBe(1);
});

it('grants a direct wildcard exception', function () {
    $this->user->grantWildcard('posts.*');
    $rows = DB::table('model_permission')
        ->where('model_type', TestUser::class)
        ->where('model_id', $this->user->id)
        ->where('wildcard', 'posts.*')
        ->count();
    expect($rows)->toBe(1);
});

it('revokes a wildcard', function () {
    $this->user->grantWildcard('posts.*');
    $this->user->revokeWildcard('posts.*');
    $rows = DB::table('model_permission')
        ->where('wildcard', 'posts.*')
        ->count();
    expect($rows)->toBe(0);
});

it('hasPermission returns true for exact role permission', function () {
    $this->role->assignPermission($this->perm);
    $this->user->assignRole('editor');
    expect($this->user->hasPermission('posts.index'))->toBeTrue();
});

it('hasPermission returns true for wildcard role permission', function () {
    $this->role->assignWildcard('posts.*');
    $this->user->assignRole('editor');
    expect($this->user->hasPermission('posts.index'))->toBeTrue();
    expect($this->user->hasPermission('posts.store'))->toBeTrue();
});

it('hasPermission returns true for global wildcard', function () {
    $this->role->assignWildcard('*');
    $this->user->assignRole('editor');
    expect($this->user->hasPermission('anything.at.all'))->toBeTrue();
});

it('hasPermission returns true for direct user permission', function () {
    $this->user->grantPermission('posts.index');
    expect($this->user->hasPermission('posts.index'))->toBeTrue();
});

it('hasPermission returns false when no permission exists', function () {
    expect($this->user->hasPermission('posts.destroy'))->toBeFalse();
});

it('hasRole returns true when user has role by slug', function () {
    $this->user->assignRole('editor');
    expect($this->user->hasRole('editor'))->toBeTrue();
});

it('hasRole returns true when user has role by id', function () {
    $this->user->assignRole('editor');
    expect($this->user->hasRole($this->role->id))->toBeTrue();
});

it('hasRole returns true when user has role by model', function () {
    $this->user->assignRole('editor');
    expect($this->user->hasRole($this->role))->toBeTrue();
});

it('hasRole returns false when user does not have role', function () {
    expect($this->user->hasRole('editor'))->toBeFalse();
});

it('hasRole returns true when user has any of the given roles', function () {
    $this->user->assignRole('editor');
    expect($this->user->hasRole(['editor', 'admin']))->toBeTrue();
});

it('hasRole returns false when user has none of the given roles', function () {
    expect($this->user->hasRole(['editor', 'admin']))->toBeFalse();
});

it('permissions() returns only exact permission models for display', function () {
    $this->user->grantPermission('posts.index');
    $this->user->grantWildcard('posts.*');
    expect($this->user->permissions()->count())->toBe(1);
});
