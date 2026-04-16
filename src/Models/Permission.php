<?php

namespace Laraditz\Jaga\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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

    protected static function booted(): void
    {
        static::deleting(function (Permission $permission) {
            // Both soft-delete and force-delete remove pivot rows
            DB::table(config('jaga.tables.role_permission'))
                ->where('permission_id', $permission->id)
                ->delete();
            DB::table(config('jaga.tables.model_permission'))
                ->where('permission_id', $permission->id)
                ->delete();
        });
    }
}
