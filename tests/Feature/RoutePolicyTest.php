<?php

use Illuminate\Support\Facades\Route;
use Laraditz\Jaga\Facades\Jaga;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;
use Laraditz\Jaga\Tests\TestUser;

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'x']);
    $this->role = Role::create(['name' => 'Author', 'slug' => 'author', 'guard_name' => 'web']);
    $this->role->assignWildcard('posts.*');
    $this->user->assignRole('author');
});

it('allows access when the custom policy passes', function () {
    Jaga::policy('posts.special', fn ($user) => true);

    Permission::create(['name' => 'posts.special', 'methods' => ['GET'], 'uri' => 'posts/special']);
    Route::middleware(['auth', 'jaga'])->get('/posts/special', fn () => 'ok')->name('posts.special');

    $this->actingAs($this->user)->get('/posts/special')->assertSuccessful();
});

it('returns 403 when the custom policy fails', function () {
    Jaga::policy('posts.special', fn ($user) => false);

    Permission::create(['name' => 'posts.special', 'methods' => ['GET'], 'uri' => 'posts/special']);
    Route::middleware(['auth', 'jaga'])->get('/posts/special', fn () => 'ok')->name('posts.special');

    $this->actingAs($this->user)->get('/posts/special')->assertForbidden();
});

it('passes the user and request to the policy callback', function () {
    $capturedUser    = null;
    $capturedRequest = null;

    Jaga::policy('posts.special', function ($user, $request) use (&$capturedUser, &$capturedRequest) {
        $capturedUser    = $user;
        $capturedRequest = $request;
        return true;
    });

    Permission::create(['name' => 'posts.special', 'methods' => ['GET'], 'uri' => 'posts/special']);
    Route::middleware(['auth', 'jaga'])->get('/posts/special', fn () => 'ok')->name('posts.special');

    $this->actingAs($this->user)->get('/posts/special');

    expect($capturedUser->id)->toBe($this->user->id);
    expect($capturedRequest)->not->toBeNull();
});

it('allows a policy to be registered for multiple routes at once', function () {
    Jaga::policy(['posts.index', 'posts.show'], fn ($user) => false);

    Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
    Permission::create(['name' => 'posts.show',  'methods' => ['GET'], 'uri' => 'posts/{id}']);
    Route::middleware(['auth', 'jaga'])->get('/posts',      fn () => 'ok')->name('posts.index');
    Route::middleware(['auth', 'jaga'])->get('/posts/{id}', fn () => 'ok')->name('posts.show');

    $this->actingAs($this->user)->get('/posts')->assertForbidden();
    $this->actingAs($this->user)->get('/posts/1')->assertForbidden();
});

it('routes without a registered policy fall through to permission check', function () {
    // policy only on posts.special — posts.index should pass via normal permission check
    Jaga::policy('posts.special', fn ($user) => false);

    Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
    Route::middleware(['auth', 'jaga'])->get('/posts', fn () => 'ok')->name('posts.index');

    $this->actingAs($this->user)->get('/posts')->assertSuccessful();
});

it('policy allows access even when user has no role permission', function () {
    // Bob has no role — but policy returns true, so he gets through
    $other = TestUser::create(['name' => 'Bob', 'email' => 'bob@test.com', 'password' => 'x']);

    Jaga::policy('posts.show', fn ($user) => true);

    Permission::create(['name' => 'posts.show', 'methods' => ['GET'], 'uri' => 'posts/{id}']);
    Route::middleware(['auth', 'jaga'])->get('/posts/{id}', fn () => 'ok')->name('posts.show');

    $this->actingAs($other)->get('/posts/1')->assertSuccessful();
});

it('policy denies access even when user has permission', function () {
    Jaga::policy('posts.show', fn ($user) => false);

    Permission::create(['name' => 'posts.show', 'methods' => ['GET'], 'uri' => 'posts/{id}']);
    Route::middleware(['auth', 'jaga'])->get('/posts/{id}', fn () => 'ok')->name('posts.show');

    // user has posts.* via author role — but policy returns false
    $this->actingAs($this->user)->get('/posts/1')->assertForbidden();
});

it('policy skips ownership check', function () {
    // override: allow anyone — ignores ownership entirely
    Jaga::policy('posts.show', fn ($user) => true);

    Permission::create(['name' => 'posts.show', 'methods' => ['GET'], 'uri' => 'posts/{id}']);
    Route::middleware(['auth', 'jaga'])->get('/posts/{id}', fn () => 'ok')->name('posts.show');

    // Alice does not own the post, but policy bypasses ownership check
    $this->actingAs($this->user)->get('/posts/1')->assertSuccessful();
});

it('policy can be registered for multiple routes to deny access', function () {
    Jaga::policy(['posts.show', 'posts.edit'], fn ($user) => false);

    Permission::create(['name' => 'posts.show', 'methods' => ['GET'], 'uri' => 'posts/{id}']);
    Permission::create(['name' => 'posts.edit', 'methods' => ['GET'], 'uri' => 'posts/{id}/edit']);
    Route::middleware(['auth', 'jaga'])->get('/posts/{id}',      fn () => 'ok')->name('posts.show');
    Route::middleware(['auth', 'jaga'])->get('/posts/{id}/edit', fn () => 'ok')->name('posts.edit');

    $this->actingAs($this->user)->get('/posts/1')->assertForbidden();
    $this->actingAs($this->user)->get('/posts/1/edit')->assertForbidden();
});
