<?php

use Laraditz\Jaga\Models\Permission;

it('creates a custom permission with correct field values', function () {
    $this->artisan('jaga:define', ['name' => 'export-reports'])
        ->assertSuccessful()
        ->expectsOutputToContain("Custom permission 'export-reports' defined.");

    $perm = Permission::where('name', 'export-reports')->first();
    expect($perm)->not->toBeNull()
        ->and($perm->is_custom)->toBeTrue()
        ->and($perm->methods)->toBe([])
        ->and($perm->uri)->toBe('')
        ->and($perm->description)->toBe('export-reports')
        ->and($perm->group)->toBeNull();
});

it('stores description and group when options are provided', function () {
    $this->artisan('jaga:define', [
        'name'          => 'export-reports',
        '--description' => 'Export reports',
        '--group'       => 'Reporting',
    ])->assertSuccessful();

    $perm = Permission::where('name', 'export-reports')->first();
    expect($perm->description)->toBe('Export reports')
        ->and($perm->group)->toBe('Reporting')
        ->and($perm->is_auto_description)->toBeFalse();
});

it('re-run with options omitted leaves existing description and group unchanged', function () {
    $this->artisan('jaga:define', [
        'name'          => 'export-reports',
        '--description' => 'Export reports',
        '--group'       => 'Reporting',
    ])->assertSuccessful();

    $this->artisan('jaga:define', ['name' => 'export-reports'])->assertSuccessful();

    $perm = Permission::where('name', 'export-reports')->first();
    expect($perm->description)->toBe('Export reports')
        ->and($perm->group)->toBe('Reporting');
});

it('re-run with new options updates description and group', function () {
    $this->artisan('jaga:define', [
        'name'          => 'export-reports',
        '--description' => 'Old description',
        '--group'       => 'Old Group',
    ])->assertSuccessful();

    $this->artisan('jaga:define', [
        'name'          => 'export-reports',
        '--description' => 'New description',
        '--group'       => 'New Group',
    ])->assertSuccessful();

    $perm = Permission::where('name', 'export-reports')->first();
    expect($perm->description)->toBe('New description')
        ->and($perm->group)->toBe('New Group');
});

it('refuses to overwrite a route-based permission and exits with failure', function () {
    Permission::create([
        'name'      => 'posts.index',
        'methods'   => ['GET'],
        'uri'       => 'posts',
        'is_custom' => false,
    ]);

    $this->artisan('jaga:define', ['name' => 'posts.index'])
        ->assertFailed();

    $perm = Permission::where('name', 'posts.index')->first();
    expect($perm->is_custom)->toBeFalse();
});

it('restores a soft-deleted custom permission', function () {
    $perm = Permission::create([
        'name'      => 'export-reports',
        'methods'   => [],
        'uri'       => '',
        'is_custom' => true,
    ]);
    $perm->delete();

    expect(Permission::where('name', 'export-reports')->exists())->toBeFalse();

    $this->artisan('jaga:define', ['name' => 'export-reports'])->assertSuccessful();

    expect(Permission::where('name', 'export-reports')->exists())->toBeTrue();
});

it('creates custom permission with is_public true when --public is passed', function () {
    $this->artisan('jaga:define', ['name' => 'webhook-receive', '--public' => true])
        ->assertSuccessful();

    expect(Permission::where('name', 'webhook-receive')->value('is_public'))->toBeTrue();
});

it('creates custom permission with is_public false when --public is not passed', function () {
    $this->artisan('jaga:define', ['name' => 'webhook-receive'])
        ->assertSuccessful();

    expect(Permission::where('name', 'webhook-receive')->value('is_public'))->toBeFalse();
});

it('updates is_public to true when --public is passed on re-run', function () {
    $this->artisan('jaga:define', ['name' => 'webhook-receive'])->assertSuccessful();
    expect(Permission::where('name', 'webhook-receive')->value('is_public'))->toBeFalse();

    $this->artisan('jaga:define', ['name' => 'webhook-receive', '--public' => true])->assertSuccessful();
    expect(Permission::where('name', 'webhook-receive')->value('is_public'))->toBeTrue();
});

it('leaves is_public unchanged when --public is not passed on re-run', function () {
    $this->artisan('jaga:define', ['name' => 'webhook-receive', '--public' => true])->assertSuccessful();
    expect(Permission::where('name', 'webhook-receive')->value('is_public'))->toBeTrue();

    $this->artisan('jaga:define', ['name' => 'webhook-receive'])->assertSuccessful();
    expect(Permission::where('name', 'webhook-receive')->value('is_public'))->toBeTrue();
});
