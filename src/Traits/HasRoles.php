<?php

namespace Laraditz\Jaga\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Models\Role;
use Laraditz\Jaga\Support\CacheManager;

trait HasRoles
{
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            Role::class,
            'model',
            config('jaga.tables.model_role'),
            null,
            'role_id'
        );
    }

    public function hasRole(Role|string|int|array $role): bool
    {
        $roles = is_array($role) ? $role : [$role];
        $slugs = [];
        $ids   = [];

        foreach ($roles as $r) {
            if ($r instanceof Role) {
                $ids[] = $r->id;
            } elseif (is_int($r)) {
                $ids[] = $r;
            } else {
                $slugs[] = $r;
            }
        }

        return $this->roles()
            ->where(function ($query) use ($slugs, $ids) {
                $query->whereIn('slug', $slugs)
                      ->orWhereIn('id', $ids);
            })
            ->exists();
    }

    /** Display-only. Never use for access checks. Use hasPermission() instead. */
    public function permissions(): Collection
    {
        return Permission::whereIn('id',
            DB::table(config('jaga.tables.model_permission'))
                ->where('model_type', static::class)
                ->where('model_id', $this->getKey())
                ->whereNotNull('permission_id')
                ->pluck('permission_id')
        )->get();
    }

    public function assignRole(Role|string|int|array $role): void
    {
        $resolved = collect(is_array($role) ? $role : [$role])
            ->map(fn ($r) => $this->resolveRole($r));

        $this->roles()->syncWithoutDetaching($resolved->pluck('id')->all());

        $cache = app(CacheManager::class);
        foreach ($resolved as $r) {
            $cache->addRoleMember($r->id, static::class, $this->getKey());
        }
        $cache->flushUser(static::class, $this->getKey());
    }

    public function removeRole(Role|string|int|array $role): void
    {
        $resolved = collect(is_array($role) ? $role : [$role])
            ->map(fn ($r) => $this->resolveRole($r));

        $this->roles()->detach($resolved->pluck('id')->all());

        $cache = app(CacheManager::class);
        foreach ($resolved as $r) {
            $cache->removeRoleMember($r->id, static::class, $this->getKey());
        }
        $cache->flushUser(static::class, $this->getKey());
    }

    public function grantPermission(Permission|string|int $permission): void
    {
        $resolved = $this->resolvePermission($permission);

        DB::table(config('jaga.tables.model_permission'))->insert([
            'model_type'    => static::class,
            'model_id'      => $this->getKey(),
            'permission_id' => $resolved->id,
            'wildcard'      => null,
            'created_at'    => now(),
        ]);

        app(CacheManager::class)->flushUser(static::class, $this->getKey());
    }

    public function revokePermission(Permission|string|int $permission): void
    {
        $resolved = $this->resolvePermission($permission);

        DB::table(config('jaga.tables.model_permission'))
            ->where('model_type', static::class)
            ->where('model_id', $this->getKey())
            ->where('permission_id', $resolved->id)
            ->delete();

        app(CacheManager::class)->flushUser(static::class, $this->getKey());
    }

    public function grantWildcard(string $pattern): void
    {
        DB::table(config('jaga.tables.model_permission'))->insert([
            'model_type'    => static::class,
            'model_id'      => $this->getKey(),
            'permission_id' => null,
            'wildcard'      => $pattern,
            'created_at'    => now(),
        ]);

        app(CacheManager::class)->flushUser(static::class, $this->getKey());
    }

    public function revokeWildcard(string $pattern): void
    {
        DB::table(config('jaga.tables.model_permission'))
            ->where('model_type', static::class)
            ->where('model_id', $this->getKey())
            ->where('wildcard', $pattern)
            ->delete();

        app(CacheManager::class)->flushUser(static::class, $this->getKey());
    }

    public function hasPermission(string $routeName): bool
    {
        $cache  = app(CacheManager::class);
        $cached = $cache->getUserPermissions(static::class, $this->getKey());

        if ($cached !== null) {
            return $this->resolveWildcard($routeName, $cached);
        }

        $data = $this->buildPermissionData();
        $cache->putUserPermissions(static::class, $this->getKey(), $data);

        return $this->resolveWildcard($routeName, $data);
    }

    private function buildPermissionData(): array
    {
        $rpTable = config('jaga.tables.role_permission');
        $mrTable = config('jaga.tables.model_role');

        $exactFromRoles = DB::table($rpTable)
            ->join($mrTable, "{$rpTable}.role_id", '=', "{$mrTable}.role_id")
            ->where("{$mrTable}.model_type", static::class)
            ->where("{$mrTable}.model_id", $this->getKey())
            ->whereNotNull("{$rpTable}.permission_id")
            ->pluck("{$rpTable}.permission_id")
            ->toArray();

        $wildcardFromRoles = DB::table($rpTable)
            ->join($mrTable, "{$rpTable}.role_id", '=', "{$mrTable}.role_id")
            ->where("{$mrTable}.model_type", static::class)
            ->where("{$mrTable}.model_id", $this->getKey())
            ->whereNotNull("{$rpTable}.wildcard")
            ->pluck("{$rpTable}.wildcard")
            ->toArray();

        $exactDirect = DB::table(config('jaga.tables.model_permission'))
            ->where('model_type', static::class)
            ->where('model_id', $this->getKey())
            ->whereNotNull('permission_id')
            ->pluck('permission_id')
            ->toArray();

        $wildcardDirect = DB::table(config('jaga.tables.model_permission'))
            ->where('model_type', static::class)
            ->where('model_id', $this->getKey())
            ->whereNotNull('wildcard')
            ->pluck('wildcard')
            ->toArray();

        $routeNames = Permission::whereIn('id', array_merge($exactFromRoles, $exactDirect))
            ->pluck('name')
            ->toArray();

        return [
            'exact'    => $routeNames,
            'wildcard' => array_merge($wildcardFromRoles, $wildcardDirect),
        ];
    }

    private function resolveWildcard(string $routeName, array $data): bool
    {
        if (in_array($routeName, $data['exact'] ?? [])) {
            return true;
        }

        foreach ($data['wildcard'] ?? [] as $pattern) {
            if ($pattern === '*') {
                return true;
            }
            $prefix = rtrim($pattern, '*');
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function resolveRole(Role|string|int $role): Role
    {
        if ($role instanceof Role) {
            return $role;
        }
        if (is_int($role)) {
            return Role::findOrFail($role);
        }
        return Role::where('slug', $role)->firstOrFail();
    }

    private function resolvePermission(Permission|string|int $permission): Permission
    {
        if ($permission instanceof Permission) {
            return $permission;
        }
        if (is_int($permission)) {
            return Permission::findOrFail($permission);
        }
        return Permission::where('name', $permission)->firstOrFail();
    }
}
