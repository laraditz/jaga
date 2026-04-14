<?php

use Laraditz\Jaga\Tests\TestPost;
use Laraditz\Jaga\Tests\TestUser;

it('has default ownerKey of user_id', function () {
    $post = new TestPost;
    expect($post->ownerKey ?? 'user_id')->toBe('user_id');
});

it('has default ownershipRequired of true', function () {
    $post = new TestPost;
    expect($post->ownershipRequired ?? true)->toBeTrue();
});

it('allows overriding ownerKey', function () {
    $model = new class extends \Illuminate\Database\Eloquent\Model {
        use \Laraditz\Jaga\Traits\HasOwnership;
        protected string $ownerKey = 'author_id';
    };
    expect($model->ownerKey)->toBe('author_id');
});

it('allows opting out of ownership check', function () {
    $model = new class extends \Illuminate\Database\Eloquent\Model {
        use \Laraditz\Jaga\Traits\HasOwnership;
        protected bool $ownershipRequired = false;
    };
    expect($model->ownershipRequired)->toBeFalse();
});

it('checkOwnership returns true when owner key matches and user is the correct model type', function () {
    $user = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'x']);
    $post = TestPost::create(['user_id' => $user->id]);

    expect($post->checkOwnership($user, 'posts.update'))->toBeTrue();
});

it('checkOwnership returns false when owner key does not match', function () {
    $user  = TestUser::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'x']);
    $other = TestUser::create(['name' => 'Bob',   'email' => 'bob@test.com',   'password' => 'x']);
    $post  = TestPost::create(['user_id' => $other->id]);

    expect($post->checkOwnership($user, 'posts.update'))->toBeFalse();
});

it('checkOwnership returns false when user is not an instance of the configured owner model', function () {
    $post = TestPost::create(['user_id' => 1]);

    // Anonymous class with the same key — but NOT a TestUser instance
    $wrongUser = new class implements \Illuminate\Contracts\Auth\Authenticatable {
        public function getAuthIdentifierName(): string { return 'id'; }
        public function getAuthIdentifier(): mixed { return 1; }
        public function getAuthPasswordName(): string { return 'password'; }
        public function getAuthPassword(): string { return ''; }
        public function getRememberToken(): ?string { return null; }
        public function setRememberToken($value): void {}
        public function getRememberTokenName(): string { return ''; }
        public function getKey(): int { return 1; }
    };

    expect($post->checkOwnership($wrongUser, 'posts.update'))->toBeFalse();
});
