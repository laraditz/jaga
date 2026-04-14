<?php

use Illuminate\Support\Facades\Route;
use Laraditz\Jaga\Models\Permission;

beforeEach(function () {
    Route::get('/posts', fn () => '')->name('posts.index');
    Route::post('/posts', fn () => '')->name('posts.store');
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
    Route::match(['GET', 'POST'], '/multi', fn () => '')->name('multi.endpoint');
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
