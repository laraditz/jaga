<?php

namespace Laraditz\Jaga\Support;

use Illuminate\Support\Facades\Cache;

class CacheManager
{
    private bool $tagsSupported;

    public function __construct()
    {
        $this->tagsSupported = method_exists(Cache::getStore(), 'tags');
    }

    public function getUserPermissions(string $modelType, int|string $modelId): ?array
    {
        if (! config('jaga.cache.enabled')) {
            return null;
        }

        $key = $this->userKey($modelType, $modelId);

        if ($this->tagsSupported) {
            return Cache::tags(['jaga', "jaga.user.{$modelType}.{$modelId}"])->get($key);
        }

        return Cache::get($key);
    }

    public function putUserPermissions(string $modelType, int|string $modelId, array $data): void
    {
        if (! config('jaga.cache.enabled')) {
            return;
        }

        $key = $this->userKey($modelType, $modelId);
        $ttl = config('jaga.cache.ttl', 3600);

        if ($this->tagsSupported) {
            Cache::tags(['jaga', "jaga.user.{$modelType}.{$modelId}"])->put($key, $data, $ttl);
        } else {
            Cache::put($key, $data, $ttl);
            $this->appendUserKey($key);
        }
    }

    public function flushUser(string $modelType, int|string $modelId): void
    {
        $key = $this->userKey($modelType, $modelId);

        if ($this->tagsSupported) {
            Cache::tags(["jaga.user.{$modelType}.{$modelId}"])->flush();
        } else {
            Cache::forget($key);
        }
    }

    public function flushAll(): void
    {
        if ($this->tagsSupported) {
            Cache::tags(['jaga'])->flush();
            Cache::forget($this->prefix('permissions'));
            Cache::forget($this->prefix('public_routes'));
            return;
        }

        Cache::forget($this->prefix('permissions'));
        Cache::forget($this->prefix('public_routes'));

        $keys = Cache::get($this->prefix('user_keys'), []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget($this->prefix('user_keys'));
    }

    public function rememberPublicRoutes(callable $callback): array
    {
        if (! config('jaga.cache.enabled')) {
            return $callback();
        }

        $key    = $this->prefix('public_routes');
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $routes = $callback();
        Cache::put($key, $routes, config('jaga.cache.ttl', 3600));

        return $routes;
    }

    public function flushPublicRoutes(): void
    {
        Cache::forget($this->prefix('public_routes'));
    }

    public function flushRoleMembers(int $roleId): void
    {
        Cache::forget($this->prefix("role.{$roleId}.members"));
    }

    public function getRoleMembers(int $roleId): array
    {
        return Cache::get($this->prefix("role.{$roleId}.members"), []);
    }

    public function addRoleMember(int $roleId, string $modelType, int|string $modelId): void
    {
        $key     = $this->prefix("role.{$roleId}.members");
        $members = Cache::get($key, []);
        $entry   = "{$modelType}.{$modelId}";

        if (! in_array($entry, $members)) {
            $members[] = $entry;
            Cache::put($key, $members, config('jaga.cache.ttl', 3600));
        }
    }

    public function removeRoleMember(int $roleId, string $modelType, int|string $modelId): void
    {
        $key     = $this->prefix("role.{$roleId}.members");
        $members = Cache::get($key, []);
        Cache::put($key, array_filter($members, fn ($m) => $m !== "{$modelType}.{$modelId}"), config('jaga.cache.ttl', 3600));
    }

    public function flushRoleMemberCaches(int $roleId): void
    {
        $members = $this->getRoleMembers($roleId);

        if (empty($members)) {
            $this->flushAll();
            return;
        }

        foreach ($members as $member) {
            [$type, $id] = explode('.', $member, 2);
            $this->flushUser($type, $id);
        }
    }

    private function userKey(string $modelType, int|string $modelId): string
    {
        $safeType = str_replace('\\', '_', $modelType);
        return $this->prefix("user.{$safeType}.{$modelId}.permissions");
    }

    private function prefix(string $key): string
    {
        return config('jaga.cache.key_prefix', 'jaga').'.'.$key;
    }

    private function appendUserKey(string $key): void
    {
        $index = $this->prefix('user_keys');
        $keys  = Cache::get($index, []);
        if (! in_array($key, $keys)) {
            $keys[] = $key;
            Cache::put($index, $keys, config('jaga.cache.ttl', 3600));
        }
    }
}
