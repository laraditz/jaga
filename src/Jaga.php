<?php

namespace Laraditz\Jaga;

class Jaga
{
    /** @var array<string, callable> */
    private array $policies = [];

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
}
