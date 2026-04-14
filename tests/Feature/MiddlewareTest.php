<?php

use Illuminate\Support\Facades\Route;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;
use Laraditz\Jaga\Tests\TestPost;
use Laraditz\Jaga\Tests\TestUser;

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com', 'password' => 'x']);
    $this->perm = Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'api/posts']);
    $this->role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'guard_name' => 'web']);
});

it('returns 401 when unauthenticated', function () {
    Route::middleware('jaga')->get('/api/posts', fn () => 'ok')->name('posts.index');
    $this->get('/api/posts')->assertUnauthorized();
});

it('returns 403 when user has no permission', function () {
    Route::middleware(['auth', 'jaga'])->get('/api/posts', fn () => 'ok')->name('posts.index');
    $this->actingAs($this->user)->get('/api/posts')->assertForbidden();
});

it('returns 200 when user has permission via role', function () {
    $this->role->assignPermission($this->perm);
    $this->user->assignRole('editor');

    Route::middleware(['auth', 'jaga'])->get('/api/posts', fn () => 'ok')->name('posts.index');
    $this->actingAs($this->user)->get('/api/posts')->assertSuccessful();
});

it('returns 200 when user has direct permission', function () {
    $this->user->grantPermission('posts.index');
    Route::middleware(['auth', 'jaga'])->get('/api/posts', fn () => 'ok')->name('posts.index');
    $this->actingAs($this->user)->get('/api/posts')->assertSuccessful();
});

it('returns 200 when user has wildcard permission', function () {
    $this->user->grantWildcard('posts.*');
    Route::middleware(['auth', 'jaga'])->get('/api/posts', fn () => 'ok')->name('posts.index');
    $this->actingAs($this->user)->get('/api/posts')->assertSuccessful();
});

it('allows unnamed routes without checking permissions', function () {
    Route::middleware(['auth', 'jaga'])->get('/unnamed-route', fn () => 'ok'); // no name
    $this->actingAs($this->user)->get('/unnamed-route')->assertSuccessful();
});

it('returns 403 when user does not own the resource', function () {
    $owner = TestUser::create(['name' => 'Bob', 'email' => 'b@test.com', 'password' => 'x']);
    $post  = TestPost::create(['user_id' => $owner->id]);

    $perm = Permission::create(['name' => 'posts.update', 'methods' => ['PUT'], 'uri' => 'api/posts/{post}']);
    $this->role->assignPermission($perm);
    $this->user->assignRole('editor');

    Route::middleware(['auth', 'jaga'])->put('/api/posts/{post}', fn (TestPost $post) => 'ok')->name('posts.update');
    $this->actingAs($this->user)->put("/api/posts/{$post->id}")->assertForbidden();
});

it('returns 200 when user owns the resource', function () {
    $post = TestPost::create(['user_id' => $this->user->id]);
    $perm = Permission::create(['name' => 'posts.update', 'methods' => ['PUT'], 'uri' => 'api/posts/{post}']);
    $this->role->assignPermission($perm);
    $this->user->assignRole('editor');

    Route::middleware(['auth', 'jaga'])->put('/api/posts/{post}', fn (TestPost $post) => 'ok')->name('posts.update');
    $this->actingAs($this->user)->put("/api/posts/{$post->id}")->assertSuccessful();
});
