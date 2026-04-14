<?php

use Illuminate\Support\Facades\Gate;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;
use Laraditz\Jaga\Tests\TestUser;

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'x']);
    Permission::create([
        'name'      => 'export-reports',
        'methods'   => [],
        'uri'       => '',
        'is_custom' => true,
    ]);
});

it('custom permission can be assigned to a role and checked via hasPermission()', function () {
    $role = Role::create(['name' => 'Reporter', 'slug' => 'reporter']);
    $role->assignPermission('export-reports');
    $this->user->assignRole($role);

    expect($this->user->hasPermission('export-reports'))->toBeTrue();
});

it('custom permission can be granted directly to a user and checked via hasPermission()', function () {
    $this->user->grantPermission('export-reports');

    expect($this->user->hasPermission('export-reports'))->toBeTrue();
});

it('custom permission works via Gate::allows()', function () {
    $this->user->grantPermission('export-reports');
    $this->actingAs($this->user);

    expect(Gate::allows('export-reports'))->toBeTrue();
});

it('user without the custom permission is denied', function () {
    expect($this->user->hasPermission('export-reports'))->toBeFalse();
});

it('wildcard role permission covers custom permissions', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    $role->assignWildcard('*');
    $this->user->assignRole($role);

    expect($this->user->hasPermission('export-reports'))->toBeTrue();
});
