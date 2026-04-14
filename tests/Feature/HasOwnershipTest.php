<?php

use Laraditz\Jaga\Tests\TestPost;

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
