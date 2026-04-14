<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;
use Laraditz\Jaga\Tests\TestPost;
use Laraditz\Jaga\Tests\TestPostPolicy;
use Laraditz\Jaga\Tests\TestSuperAdminPostPolicy;
use Laraditz\Jaga\Tests\TestUser;

beforeEach(function () {
    Gate::policy(TestPost::class, TestPostPolicy::class);

    $this->owner = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'x']);
    $this->other = TestUser::create(['name' => 'Bob',   'email' => 'bob@test.com',   'password' => 'x']);
    $this->post  = TestPost::create(['user_id' => $this->owner->id]);

    $role = Role::create(['name' => 'Author', 'slug' => 'author', 'guard_name' => 'web']);
    $role->assignWildcard('posts.*');
    $this->owner->assignRole('author');
    $this->other->assignRole('author');  // Bob has the role — Policy decides ownership

    Permission::create(['name' => 'posts.show',    'methods' => ['GET'],    'uri' => 'posts/{post}']);
    Permission::create(['name' => 'posts.update',  'methods' => ['PUT'],    'uri' => 'posts/{post}']);
    Permission::create(['name' => 'posts.destroy', 'methods' => ['DELETE'], 'uri' => 'posts/{post}']);

    Route::middleware(['auth', 'jaga'])->get('/posts/{post}',    fn (TestPost $post) => 'ok')->name('posts.show');
    Route::middleware(['auth', 'jaga'])->put('/posts/{post}',    fn (TestPost $post) => 'ok')->name('posts.update');
    Route::middleware(['auth', 'jaga'])->delete('/posts/{post}', fn (TestPost $post) => 'ok')->name('posts.destroy');
});

it('allows the owner to view their post via Policy', function () {
    $this->actingAs($this->owner)->get("/posts/{$this->post->id}")->assertSuccessful();
});

it('denies a non-owner from viewing the post via Policy', function () {
    $this->actingAs($this->other)->get("/posts/{$this->post->id}")->assertForbidden();
});

it('allows the owner to update their post via Policy', function () {
    $this->actingAs($this->owner)->put("/posts/{$this->post->id}")->assertSuccessful();
});

it('denies a non-owner from updating the post via Policy', function () {
    $this->actingAs($this->other)->put("/posts/{$this->post->id}")->assertForbidden();
});

it('allows the owner to delete their post via Policy', function () {
    $this->actingAs($this->owner)->delete("/posts/{$this->post->id}")->assertSuccessful();
});

it('denies a non-owner from deleting the post via Policy', function () {
    $this->actingAs($this->other)->delete("/posts/{$this->post->id}")->assertForbidden();
});

it('Policy takes precedence over HasOwnership when both are present', function () {
    // TestPost uses HasOwnership (user_id) but Policy is registered — Policy wins.
    // Policy says only the owner can view, regardless of what HasOwnership would say.
    expect($this->post->user_id)->toBe($this->owner->id);
    $this->actingAs($this->other)->get("/posts/{$this->post->id}")->assertForbidden();
    $this->actingAs($this->owner)->get("/posts/{$this->post->id}")->assertSuccessful();
});

it('Policy before() can grant superadmin access regardless of ownership', function () {
    Gate::policy(TestPost::class, TestSuperAdminPostPolicy::class);

    $admin     = TestUser::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'x']);
    $adminRole = Role::create(['name' => 'Superadmin', 'slug' => 'superadmin', 'guard_name' => 'web']);
    $adminRole->assignWildcard('*');
    $admin->assignRole('superadmin');

    // Admin does not own the post, but Policy::before() short-circuits and grants full access
    $this->actingAs($admin)->get("/posts/{$this->post->id}")->assertSuccessful();

    // A regular user (non-superadmin) still hits the view() check and is denied
    $this->actingAs($this->other)->get("/posts/{$this->post->id}")->assertForbidden();
});

it('HasOwnership is still used when no Policy is registered for the model', function () {
    // Reset: no Policy registered for TestPost
    Gate::policy(TestPost::class, null);

    $this->actingAs($this->other)->put("/posts/{$this->post->id}")->assertForbidden();
    $this->actingAs($this->owner)->put("/posts/{$this->post->id}")->assertSuccessful();
});

it('calls a custom Policy method matching the route action name', function () {
    Permission::create(['name' => 'posts.publish', 'methods' => ['POST'], 'uri' => 'posts/{post}/publish']);
    Route::middleware(['auth', 'jaga'])->post('/posts/{post}/publish', fn (TestPost $post) => 'ok')->name('posts.publish');

    // TestPostPolicy::publish() checks ownership — only owner can publish
    $this->actingAs($this->owner)->post("/posts/{$this->post->id}/publish")->assertSuccessful();
    $this->actingAs($this->other)->post("/posts/{$this->post->id}/publish")->assertForbidden();
});

it('skips Policy check when route action has no matching standard or custom method', function () {
    Permission::create(['name' => 'posts.stats', 'methods' => ['GET'], 'uri' => 'posts/{post}/stats']);
    Route::middleware(['auth', 'jaga'])->get('/posts/{post}/stats', fn (TestPost $post) => 'ok')->name('posts.stats');

    // TestPostPolicy has no 'stats' method — no model-level check runs
    // Bob has the posts.* wildcard so he passes the permission check unblocked
    $this->actingAs($this->other)->get("/posts/{$this->post->id}/stats")->assertSuccessful();
});
