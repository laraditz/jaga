<?php

namespace Laraditz\Jaga\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'methods'             => 'array',
            'is_auto_description' => 'boolean',
            'is_custom'           => 'boolean',
            'access_level'        => \Laraditz\Jaga\Enums\AccessLevel::class,
        ];
    }

    public function getTable(): string
    {
        return config('jaga.tables.permissions');
    }
}
