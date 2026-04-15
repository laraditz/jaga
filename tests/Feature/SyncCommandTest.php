<?php

use Illuminate\Support\Facades\Route;
use Laraditz\Jaga\Models\Permission;

beforeEach(function () {
    Route::middleware('jaga')->get('/posts', fn () => '')->name('posts.index');
    Route::middleware('jaga')->post('/posts', fn () => '')->name('posts.store');
});

it('creates permissions for named routes', function () {
    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->exists())->toBeTrue();
    expect(Permission::where('name', 'posts.store')->exists())->toBeTrue();
});

it('auto-generates descriptions', function () {
    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->value('description'))->toBe('List all posts');
    expect(Permission::where('name', 'posts.store')->value('description'))->toBe('Create a post');
});

it('does not overwrite manually edited descriptions', function () {
    $this->artisan('jaga:sync')->assertSuccessful();
    Permission::where('name', 'posts.index')
        ->update(['description' => 'Custom desc', 'is_auto_description' => false]);

    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->value('description'))->toBe('Custom desc');
});

it('re-generates description on existing auto-described permissions', function () {
    Permission::create([
        'name' => 'posts.index',
        'methods'    => ['GET'],
        'uri'        => 'posts',
        'description' => 'Old desc',
        'is_auto_description' => true,
    ]);

    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->value('description'))->toBe('List all posts');
});

it('skips unnamed routes', function () {
    Route::get('/unnamed', fn () => ''); // no name
    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::count())->toBe(2); // only the 2 named routes
});

it('restores a previously soft-deleted permission when its route reappears', function () {
    $this->artisan('jaga:sync')->assertSuccessful();
    Permission::where('name', 'posts.index')->delete(); // manually soft-delete

    expect(Permission::where('name', 'posts.index')->exists())->toBeFalse();

    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->exists())->toBeTrue();
});

it('stores multiple http methods as json for multi-method routes', function () {
    Route::middleware('jaga')->match(['GET', 'POST'], '/multi', fn () => '')->name('multi.endpoint');
    $this->artisan('jaga:sync')->assertSuccessful();

    $perm = Permission::where('name', 'multi.endpoint')->first();
    expect($perm->methods)->toContain('GET')->toContain('POST');
});

it('auto-sets group on new permissions from route name', function () {
    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->value('group'))->toBe('Posts');
    expect(Permission::where('name', 'posts.store')->value('group'))->toBe('Posts');
});

it('sets group on existing permissions where group is null', function () {
    Permission::create([
        'name'    => 'posts.index',
        'methods' => ['GET'],
        'uri'     => 'posts',
        'group'   => null,
        'is_auto_description' => true,
    ]);

    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->value('group'))->toBe('Posts');
});

it('does not overwrite group on existing permissions that already have a value', function () {
    Permission::create([
        'name'    => 'posts.index',
        'methods' => ['GET'],
        'uri'     => 'posts',
        'group'   => 'Custom Group',
        'is_auto_description' => true,
    ]);

    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->value('group'))->toBe('Custom Group');
});

it('does not soft-delete custom permissions during sync', function () {
    Permission::create([
        'name'      => 'export-reports',
        'methods'   => [],
        'uri'       => '',
        'is_custom' => true,
    ]);

    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'export-reports')->exists())->toBeTrue();
});

it('does not overwrite methods/uri/description on a custom permission whose name collides with a route', function () {
    Permission::create([
        'name'        => 'posts.index',
        'methods'     => [],
        'uri'         => '',
        'description' => 'My custom desc',
        'is_custom'   => true,
        'is_auto_description' => false,
    ]);

    $this->artisan('jaga:sync')->assertSuccessful();

    $perm = Permission::where('name', 'posts.index')->first();
    expect($perm->methods)->toBe([]);
    expect($perm->uri)->toBe('');
    expect($perm->description)->toBe('My custom desc');
});

it('emits a warning when a custom permission name collides with a route name', function () {
    Permission::create([
        'name'      => 'posts.index',
        'methods'   => [],
        'uri'       => '',
        'is_custom' => true,
    ]);

    $this->artisan('jaga:sync')
        ->assertSuccessful()
        ->expectsOutputToContain('Custom permission "posts.index" conflicts with a route of the same name');
});

it('does not sync routes without jaga middleware', function () {
    Route::get('/public-page', fn () => '')->name('public.page');
    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'public.page')->exists())->toBeFalse();
});

it('sets is_public true for route with jaga but no auth middleware', function () {
    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->value('is_public'))->toBeTrue();
    expect(Permission::where('name', 'posts.store')->value('is_public'))->toBeTrue();
});

it('sets is_public false for route with jaga + auth middleware', function () {
    Route::middleware(['jaga', 'auth'])->get('/dashboard', fn () => '')->name('dashboard');
    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'dashboard')->value('is_public'))->toBeFalse();
});

it('does not overwrite is_public on re-sync', function () {
    $this->artisan('jaga:sync')->assertSuccessful();

    // Admin manually changes is_public
    Permission::where('name', 'posts.index')->update(['is_public' => false]);

    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::where('name', 'posts.index')->value('is_public'))->toBeFalse();
});

it('does not soft-delete routes without jaga middleware (they were never synced)', function () {
    $this->artisan('jaga:sync')->assertSuccessful();
    expect(Permission::withTrashed()->where('name', 'public.page')->exists())->toBeFalse();
});

// Config override tests
it('config override sets description and marks is_auto_description false on new permission', function () {
    config(['jaga.permissions.posts.index' => ['description' => 'Pinned description']]);

    $this->artisan('jaga:sync')->assertSuccessful();

    $perm = Permission::where('name', 'posts.index')->first();
    expect($perm->description)->toBe('Pinned description')
        ->and($perm->is_auto_description)->toBeFalse();
});

it('config override sets group on new permission', function () {
    config(['jaga.permissions.posts.index' => ['group' => 'Content']]);

    $this->artisan('jaga:sync')->assertSuccessful();

    expect(Permission::where('name', 'posts.index')->value('group'))->toBe('Content');
});

it('omitting description from config override auto-generates it', function () {
    config(['jaga.permissions.posts.index' => ['group' => 'Content']]);

    $this->artisan('jaga:sync')->assertSuccessful();

    $perm = Permission::where('name', 'posts.index')->first();
    expect($perm->description)->toBe('List all posts')
        ->and($perm->is_auto_description)->toBeTrue();
});

it('config override applies description on re-sync even when is_auto_description was already false', function () {
    Permission::create([
        'name'                => 'posts.index',
        'methods'             => ['GET'],
        'uri'                 => 'posts',
        'description'         => 'Old manual desc',
        'is_auto_description' => false,
    ]);

    config(['jaga.permissions.posts.index' => ['description' => 'Config pinned']]);

    $this->artisan('jaga:sync')->assertSuccessful();

    expect(Permission::where('name', 'posts.index')->value('description'))->toBe('Config pinned');
});

it('config override applies group on re-sync even when group was already set', function () {
    Permission::create([
        'name'    => 'posts.index',
        'methods' => ['GET'],
        'uri'     => 'posts',
        'group'   => 'Old Group',
        'is_auto_description' => true,
    ]);

    config(['jaga.permissions.posts.index' => ['group' => 'Overridden Group']]);

    $this->artisan('jaga:sync')->assertSuccessful();

    expect(Permission::where('name', 'posts.index')->value('group'))->toBe('Overridden Group');
});

it('partial override (only description) leaves group managed by existing logic', function () {
    config(['jaga.permissions.posts.index' => ['description' => 'Only desc pinned']]);

    $this->artisan('jaga:sync')->assertSuccessful();

    $perm = Permission::where('name', 'posts.index')->first();
    expect($perm->description)->toBe('Only desc pinned')
        ->and($perm->group)->toBe('Posts'); // auto-generated
});

it('route with no config entry behaves identically to current sync behaviour', function () {
    $this->artisan('jaga:sync')->assertSuccessful();

    $perm = Permission::where('name', 'posts.store')->first();
    expect($perm->description)->toBe('Create a post')
        ->and($perm->is_auto_description)->toBeTrue()
        ->and($perm->group)->toBe('Posts');
});
