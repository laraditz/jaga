<?php

use Illuminate\Support\Facades\Blade;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;
use Laraditz\Jaga\Tests\TestUser;

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'x']);
    $this->role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'guard_name' => 'web']);
    $this->perm = Permission::create(['name' => 'posts.index', 'methods' => ['GET'], 'uri' => 'posts']);
});

// --- @role / @endrole ---

it('@role renders content when user has the role', function () {
    $this->user->assignRole('editor');
    $this->actingAs($this->user);

    expect(trim(Blade::render("@role('editor')\nyes\n@endrole")))->toBe('yes');
});

it('@role hides content when user does not have the role', function () {
    $this->actingAs($this->user);

    expect(trim(Blade::render("@role('editor')\nyes\n@endrole")))->toBe('');
});

it('@role hides content when unauthenticated', function () {
    expect(trim(Blade::render("@role('editor')\nyes\n@endrole")))->toBe('');
});

it('@role renders else branch when user does not have the role', function () {
    $this->actingAs($this->user);

    expect(trim(Blade::render("@role('editor')\nyes\n@else\nno\n@endrole")))->toBe('no');
});

it('@role accepts an array and renders content when user has any matching role', function () {
    $this->user->assignRole('editor');
    $this->actingAs($this->user);

    expect(trim(Blade::render("@role(['editor', 'admin'])\nyes\n@endrole")))->toBe('yes');
});

it('@role renders content for an explicitly passed model with the role', function () {
    $other = TestUser::create(['name' => 'Bob', 'email' => 'bob@test.com', 'password' => 'x']);
    $other->assignRole('editor');

    expect(trim(Blade::render('@role(\'editor\', $record)'."\nyes\n".'@endrole', ['record' => $other])))->toBe('yes');
});

it('@role hides content when the explicitly passed model lacks the role', function () {
    expect(trim(Blade::render('@role(\'editor\', $record)'."\nyes\n".'@endrole', ['record' => $this->user])))->toBe('');
});

// --- @permission / @endpermission ---

it('@permission renders content when user has the permission', function () {
    $this->user->grantPermission('posts.index');
    $this->actingAs($this->user);

    expect(trim(Blade::render("@permission('posts.index')\nyes\n@endpermission")))->toBe('yes');
});

it('@permission renders content when user has permission via role', function () {
    $this->role->assignPermission($this->perm);
    $this->user->assignRole('editor');
    $this->actingAs($this->user);

    expect(trim(Blade::render("@permission('posts.index')\nyes\n@endpermission")))->toBe('yes');
});

it('@permission hides content when user does not have the permission', function () {
    $this->actingAs($this->user);

    expect(trim(Blade::render("@permission('posts.index')\nyes\n@endpermission")))->toBe('');
});

it('@permission hides content when unauthenticated', function () {
    expect(trim(Blade::render("@permission('posts.index')\nyes\n@endpermission")))->toBe('');
});

it('@permission renders content for an explicitly passed model with the permission', function () {
    $other = TestUser::create(['name' => 'Bob', 'email' => 'bob@test.com', 'password' => 'x']);
    $other->grantPermission('posts.index');

    expect(trim(Blade::render('@permission(\'posts.index\', $record)'."\nyes\n".'@endpermission', ['record' => $other])))->toBe('yes');
});

it('@permission hides content when the explicitly passed model lacks the permission', function () {
    expect(trim(Blade::render('@permission(\'posts.index\', $record)'."\nyes\n".'@endpermission', ['record' => $this->user])))->toBe('');
});

it('@permission renders else branch when user lacks the permission', function () {
    $this->actingAs($this->user);

    expect(trim(Blade::render("@permission('posts.index')\nyes\n@else\nno\n@endpermission")))->toBe('no');
});
