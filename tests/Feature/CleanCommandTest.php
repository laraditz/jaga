<?php

use Laraditz\Jaga\Models\Permission;

it('jaga:clean force-deletes soft-deleted permissions', function () {
    $perm = Permission::create(['name' => 'old.route', 'methods' => ['GET'], 'uri' => 'old']);
    $perm->delete();
    expect(Permission::withTrashed()->where('name', 'old.route')->exists())->toBeTrue();

    $this->artisan('jaga:clean')->assertSuccessful();
    expect(Permission::withTrashed()->where('name', 'old.route')->exists())->toBeFalse();
});
