<?php

namespace Laraditz\Jaga\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\ImplicitRouteBinding;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laraditz\Jaga\Jaga;
use Laraditz\Jaga\Models\Permission;
use Laraditz\Jaga\Support\CacheManager;
use Symfony\Component\HttpFoundation\Response;

class JagaMiddleware
{
    public function __construct(
        private Jaga $jaga,
        private CacheManager $cache,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        // Unnamed routes are never restricted
        if (! $routeName) {
            return $next($request);
        }

        // Public routes bypass auth and permission checks entirely
        if ($this->isPublicRoute($routeName)) {
            return $next($request);
        }

        $guard = $this->resolveGuard($request);
        $user  = Auth::guard($guard)->user();

        if (! $user) {
            abort(401);
        }

        // Force implicit model binding so route parameters are resolved to model instances
        // (JagaMiddleware may run before SubstituteBindings middleware)
        try {
            ImplicitRouteBinding::resolveForRoute(app(), $request->route());
        } catch (\Throwable) {
            // ignore - binding may already be resolved or not applicable
        }

        // Custom policy: completely replaces the built-in permission + ownership checks
        $policy = $this->jaga->getPolicyFor($routeName);
        if ($policy !== null) {
            if (! $policy($user, $request)) {
                abort(403);
            }

            return $next($request);
        }

        // Built-in permission check
        if (! $user->hasPermission($routeName)) {
            abort(403);
        }

        // Model-level check: Policy (when registered) takes precedence over HasOwnership
        foreach ($request->route()->parameters() as $value) {
            if (! is_object($value)) {
                continue;
            }

            $policy = Gate::getPolicyFor($value);
            if ($policy !== null) {
                $policyMethod = $this->mapToPolicyMethod($routeName, $policy);
                if ($policyMethod !== null && Gate::forUser($user)->denies($policyMethod, $value)) {
                    abort(403);
                }
                continue;
            }

            if (! method_exists($value, 'isOwnershipRequired') || ! $value->isOwnershipRequired()) {
                continue;
            }

            $ownerKey   = $value->getOwnerKey();
            $ownerModel = $value->getOwnerModel();

            if (! ($user instanceof $ownerModel) || (string) $value->{$ownerKey} !== (string) $user->getKey()) {
                abort(403);
            }
        }

        return $next($request);
    }

    private function isPublicRoute(string $routeName): bool
    {
        $public = $this->cache->rememberPublicRoutes(
            fn () => Permission::where('is_public', true)
                ->whereNull('deleted_at')
                ->pluck('name')
                ->toArray()
        );

        return in_array($routeName, $public);
    }

    private function mapToPolicyMethod(string $routeName, object $policy): ?string
    {
        $action = Str::afterLast($routeName, '.');

        // Standard RESTful mapping
        $mapped = match ($action) {
            'show'           => 'view',
            'edit', 'update' => 'update',
            'destroy'        => 'delete',
            'restore'        => 'restore',
            'forceDelete'    => 'forceDelete',
            default          => null,
        };

        if ($mapped !== null) {
            return $mapped;
        }

        // Custom policy method: if the Policy defines a method matching the route action, use it
        return method_exists($policy, $action) ? $action : null;
    }

    private function resolveGuard(Request $request): string
    {
        $middleware = $request->route()?->gatherMiddleware() ?? [];

        foreach ($middleware as $m) {
            if (str_starts_with($m, 'auth:')) {
                return substr($m, 5);
            }
        }

        return config('jaga.guard', 'web');
    }
}
