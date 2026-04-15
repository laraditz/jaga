<?php

namespace Laraditz\Jaga\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('jaga.tables.roles');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            config('jaga.tables.role_permission'),
            'role_id',
            'permission_id'
        )->withPivot('wildcard');
    }

    public function assignPermission(Permission|string $permission): void
    {
        $resolved = $permission instanceof Permission
            ? $permission
            : Permission::where('name', $permission)->firstOrFail();

        $this->permissions()->syncWithoutDetaching([$resolved->id]);
        $this->flushMembersCache();
    }

    public function assignWildcard(string $pattern): void
    {
        \Illuminate\Support\Facades\DB::table(config('jaga.tables.role_permission'))->insert([
            'role_id'    => $this->id,
            'wildcard'   => $pattern,
            'created_at' => now(),
        ]);
        $this->flushMembersCache();
    }

    private function flushMembersCache(): void
    {
        app(\Laraditz\Jaga\Support\CacheManager::class)->flushRoleMembers($this->id);
    }
}
