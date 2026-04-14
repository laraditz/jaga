<?php

namespace Laraditz\Jaga;

class Jaga
{
    /** @var array<string, callable> */
    private array $policies = [];

    /** @var array<string, callable> */
    private array $ownershipPolicies = [];

    /**
     * Register a custom authorization callback for one or more named routes.
     *
     * When a policy is registered for a route, it completely replaces the
     * built-in permission and ownership checks. The callback receives
     * ($user, $request) and must return bool.
     *
     * @param  string|array<string>  $routeName
     */
    public function policy(string|array $routeName, callable $callback): void
    {
        foreach ((array) $routeName as $name) {
            $this->policies[$name] = $callback;
        }
    }

    public function getPolicyFor(string $routeName): ?callable
    {
        return $this->policies[$routeName] ?? null;
    }

    /**
     * Register a custom ownership callback for one or more named routes.
     *
     * The callback receives ($user, $model) and must return bool.
     * Takes priority over the model's checkOwnership() method.
     * Does NOT replace the permission check (unlike Jaga::policy()).
     *
     * @param  string|array<string>  $routeName
     */
    public function ownershipPolicy(string|array $routeName, callable $callback): void
    {
        foreach ((array) $routeName as $name) {
            $this->ownershipPolicies[$name] = $callback;
        }
    }

    public function getOwnershipPolicyFor(string $routeName): ?callable
    {
        return $this->ownershipPolicies[$routeName] ?? null;
    }
}
