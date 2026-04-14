<?php

use Illuminate\Support\Facades\Schema;

it('creates all jaga tables', function () {
    expect(Schema::hasTable('roles'))->toBeTrue()
        ->and(Schema::hasTable('permissions'))->toBeTrue()
        ->and(Schema::hasTable('model_role'))->toBeTrue()
        ->and(Schema::hasTable('role_permission'))->toBeTrue()
        ->and(Schema::hasTable('model_permission'))->toBeTrue();
});

it('permissions table has soft deletes', function () {
    expect(Schema::hasColumn('permissions', 'deleted_at'))->toBeTrue();
});

it('permissions table has the expected columns', function () {
    expect(Schema::hasColumn('permissions', 'name'))->toBeTrue()
        ->and(Schema::hasColumn('permissions', 'is_custom'))->toBeTrue()
        ->and(Schema::hasColumn('permissions', 'group'))->toBeTrue();
});

it('permissions table has is_public column', function () {
    expect(Schema::hasColumn('permissions', 'is_public'))->toBeTrue();
});
