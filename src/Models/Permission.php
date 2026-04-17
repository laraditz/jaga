<?php

namespace Laraditz\Jaga\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laraditz\Jaga\Enums\AccessLevel;

class Permission extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'methods' => 'array',
            'is_auto_description' => 'boolean',
            'is_custom' => 'boolean',
            'access_level' => AccessLevel::class,
        ];
    }

    public function getTable(): string
    {
        return config('jaga.tables.permissions');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('jaga.tables.role_permission'),
            'permission_id',
            'role_id'
        );
    }

    public function users(): MorphToMany
    {
        $userModel = config('filament-jaga.user_model');

        return $this->morphedByMany(
            $userModel,
            'model',
            config('jaga.tables.model_permission'),
            'permission_id',
            'model_id'
        )->wherePivotNotNull('permission_id');
    }
}
