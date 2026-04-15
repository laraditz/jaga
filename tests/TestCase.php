<?php

namespace Laraditz\Jaga\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laraditz\Jaga\JagaServiceProvider;
use Laraditz\Jaga\Traits\HasRoles;
use Laraditz\Jaga\Traits\HasOwnership;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app): array
    {
        return [JagaServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('auth.guards.web.provider', 'users');
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('jaga.ownership.owner_model', TestUser::class);
    }
}

class TestUser extends Authenticatable
{
    use HasRoles;
    protected $table = 'users';
    protected $guarded = [];
}

class TestPost extends Model
{
    use HasOwnership;
    protected $table = 'posts';
    protected $guarded = [];
}

class TestPostPolicy
{
    public function view(TestUser $user, TestPost $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function update(TestUser $user, TestPost $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(TestUser $user, TestPost $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function publish(TestUser $user, TestPost $post): bool
    {
        return $user->id === $post->user_id;
    }
}

class TestSuperAdminPostPolicy
{
    public function before(TestUser $user): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    public function view(TestUser $user, TestPost $post): bool
    {
        return $user->id === $post->user_id;
    }
}
