<?php

use Illuminate\Support\Facades\Gate;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;
use Laraditz\Jaga\Tests\TestUser;

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'x']);
    $this->role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'guard_name' => 'web']);
    Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
    Permission::create(['name' => 'posts.show',  'methods' => ['GET'], 'uri' => 'posts/{id}']);
});

it('Gate::allows returns true when user has exact permission via role', function () {
    $this->role->permissions()->attach(
        \Laraditz\Jaga\Models\Permission::where('name', 'posts.index')->first()
    );
    $this->user->assignRole('editor');

    expect(Gate::forUser($this->user)->allows('posts.index'))->toBeTrue();
});

it('Gate::allows returns true when user has wildcard permission via role', function () {
    $this->role->assignWildcard('posts.*');
    $this->user->assignRole('editor');

    expect(Gate::forUser($this->user)->allows('posts.show'))->toBeTrue();
});

it('Gate::allows returns true when user has global wildcard', function () {
    $this->role->assignWildcard('*');
    $this->user->assignRole('editor');

    expect(Gate::forUser($this->user)->allows('posts.index'))->toBeTrue();
});

it('Gate::allows returns true when user has direct permission', function () {
    $permission = \Laraditz\Jaga\Models\Permission::where('name', 'posts.index')->first();
    $this->user->grantPermission($permission);

    expect(Gate::forUser($this->user)->allows('posts.index'))->toBeTrue();
});

it('Gate::denies returns true when user has no Jaga permission', function () {
    // user has no role or direct permission
    expect(Gate::forUser($this->user)->denies('posts.index'))->toBeTrue();
});

it('user->can returns true with Jaga permission via role', function () {
    $this->role->assignWildcard('posts.*');
    $this->user->assignRole('editor');

    expect($this->user->can('posts.show'))->toBeTrue();
});

it('user->cannot returns true when Jaga denies', function () {
    expect($this->user->cannot('posts.show'))->toBeTrue();
});

it('non-Jaga Gate abilities are unaffected', function () {
    Gate::define('do-something-custom', fn ($user) => true);

    // user has no Jaga permissions, but the Gate definition should still grant access
    expect(Gate::forUser($this->user)->allows('do-something-custom'))->toBeTrue();
});

it('developer-defined Gate ability can grant access even when Jaga denies', function () {
    // user has no role — Jaga returns null, Gate definition runs next
    Gate::define('posts.index', fn ($user) => true);

    expect(Gate::forUser($this->user)->allows('posts.index'))->toBeTrue();
});
