<?php

use Illuminate\Support\Facades\DB;
use Laraditz\Jaga\Enums\AccessLevel;

beforeEach(function () {
    // Use a temp directory for all generated seeder files in this suite
    config(['jaga.seeder.path' => sys_get_temp_dir() . '/JagaSeederTest_' . getmypid() . '.php']);
});

afterEach(function () {
    $path = config('jaga.seeder.path');
    if (file_exists($path)) {
        unlink($path);
    }
});

it('generates a seeder file at the configured path', function () {
    $path = config('jaga.seeder.path');

    $this->artisan('jaga:seeder')->assertSuccessful();

    expect(file_exists($path))->toBeTrue();
});

it('fails when file already exists and --force is not passed', function () {
    $path = config('jaga.seeder.path');
    file_put_contents($path, '<?php // existing');

    $this->artisan('jaga:seeder')->assertFailed();
});

it('overwrites existing file when --force is passed', function () {
    $path = config('jaga.seeder.path');
    file_put_contents($path, '<?php // existing');

    $this->artisan('jaga:seeder', ['--force' => true])->assertSuccessful();

    expect(file_get_contents($path))->not->toContain('// existing');
});

it('generated file contains correct roles, permissions, and role_permission data including wildcard rows', function () {
    $role = DB::table(config('jaga.tables.roles'))->insertGetId([
        'name'       => 'Editor',
        'slug'       => 'editor',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $perm = DB::table(config('jaga.tables.permissions'))->insertGetId([
        'name'                => 'posts.index',
        'methods'             => '["GET"]',
        'uri'                 => 'posts',
        'description'         => 'List all posts',
        'is_auto_description' => 1,
        'is_custom'           => 0,
        'access_level'        => AccessLevel::Public->value,
        'group'               => 'Posts',
        'created_at'          => now(),
        'updated_at'          => now(),
        'deleted_at'          => null,
    ]);

    // Permission-based row
    DB::table(config('jaga.tables.role_permission'))->insert([
        'role_id'       => $role,
        'permission_id' => $perm,
        'wildcard'      => null,
        'created_at'    => now(),
    ]);

    // Wildcard row
    DB::table(config('jaga.tables.role_permission'))->insert([
        'role_id'       => $role,
        'permission_id' => null,
        'wildcard'      => 'reports.*',
        'created_at'    => now(),
    ]);

    $this->artisan('jaga:seeder')->assertSuccessful();

    $contents = file_get_contents(config('jaga.seeder.path'));

    expect($contents)
        ->toContain("'Editor'")
        ->toContain("'posts.index'")
        ->toContain("'reports.*'")
        ->toContain("'permission_id' => NULL")  // wildcard row has null permission_id
        ->toContain("'wildcard' => NULL");        // permission row has null wildcard
});

it('generated PHP is syntactically valid', function () {
    $this->artisan('jaga:seeder')->assertSuccessful();

    $path = config('jaga.seeder.path');
    $output = shell_exec(PHP_BINARY . ' -l ' . escapeshellarg($path));

    expect($output)->toContain('No syntax errors');
});

it('respects custom seeder path from config', function () {
    $customPath = sys_get_temp_dir() . '/CustomJagaSeeder_' . getmypid() . '.php';
    config(['jaga.seeder.path' => $customPath]);

    $this->artisan('jaga:seeder')->assertSuccessful();

    expect(file_exists($customPath))->toBeTrue();

    unlink($customPath);
});

it('running the generated seeder truncates and re-inserts data correctly', function () {
    $path = sys_get_temp_dir() . '/JagaSeederRun_' . getmypid() . '_' . uniqid() . '.php';
    config(['jaga.seeder.path' => $path]);

    // Seed some data
    DB::table(config('jaga.tables.roles'))->insert([
        'name' => 'Admin', 'slug' => 'admin', 'guard_name' => 'web',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('jaga:seeder', ['--force' => true])->assertSuccessful();

    // Wipe the DB to verify seeder restores it
    DB::table(config('jaga.tables.role_permission'))->truncate();
    DB::table(config('jaga.tables.permissions'))->truncate();
    DB::table(config('jaga.tables.roles'))->truncate();

    expect(DB::table(config('jaga.tables.roles'))->count())->toBe(0);

    // Load and run the generated seeder.
    // class_exists guard prevents "cannot redeclare class" if the suite runs
    // more than once in the same PHP process (e.g. --watch mode).
    if (! class_exists(\Database\Seeders\JagaSeeder::class)) {
        require $path;
    }
    (new \Database\Seeders\JagaSeeder())->run();

    expect(DB::table(config('jaga.tables.roles'))->count())->toBe(1);
    expect(DB::table(config('jaga.tables.roles'))->value('slug'))->toBe('admin');

    unlink($path);
});
