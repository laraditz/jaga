# Jaga

Route-based RBAC for Laravel with auto-synced permissions, wildcard support, and ownership control.

> "Jaga" is Malay for "to guard / watch over".

---

## Why Jaga?

Most RBAC packages require you to define permissions twice — once in code, once in the database. Jaga eliminates that redundancy:

- Permissions are **derived from your named routes** — run `jaga:sync` and you're done
- **Custom permissions** for any action that has no route — `jaga:define export-reports` and it's in the same table, assignable and checkable the same way
- Auto-generated human-readable descriptions and groupings (e.g. `posts.store` → "Create a post", group `Posts`)
- **Wildcard permissions** (`posts.*`, `*`) for role-level flexibility
- **Ownership enforcement** at the model level — no extra policy boilerplate for simple "you must own this resource" checks
- **First-class caching** with cache tag support and a non-tagged fallback

## Requirements

- PHP 8.1+
- Laravel 10 / 11 / 12 / 13

## Installation

```bash
composer require laraditz/jaga
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=jaga-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=jaga-config
```

## Quick Start

**1. Add `HasRoles` to your authenticatable model:**

```php
use Laraditz\Jaga\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

**2. Protect your routes with the `jaga` middleware:**

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'jaga'])->group(function () {
    Route::apiResource('posts', PostController::class);
});
```

**3. Sync your routes to the permissions table:**

```bash
php artisan jaga:sync
```

**4. Assign roles to users:**

```php
// Single role
$user->assignRole('editor');

// Multiple roles at once
$user->assignRole(['editor', 'moderator']);
```

That's it. Users with the `editor` role will only be able to access routes their role has been granted.

---

## Core Concepts

### Route-Based Permissions

Permissions come from your named routes — not from hand-written definitions. Running `jaga:sync` reads Laravel's route collection and upserts the `permissions` table automatically. It also auto-assigns a `group` (derived from the route name, e.g. `posts.index` → group `Posts`) so admin UIs can organise permissions without extra configuration. If a route disappears, its permission is soft-deleted. Run `jaga:clean` to permanently remove stale records after review.

### Custom Permissions

Not every permission maps to a route. Use `jaga:define` to create permissions for arbitrary application actions — they live in the same `permissions` table and work through the same assignment and checking APIs.

```bash
php artisan jaga:define export-reports --description="Export reports" --group="Reporting"
php artisan jaga:define manage-billing --description="Manage billing" --group="Billing"
```

Or create them programmatically in a seeder or migration:

```php
Permission::create([
    'name'        => 'export-reports',
    'description' => 'Export reports',
    'group'       => 'Reporting',
    'is_custom'   => true,
    'methods'     => [],
    'uri'         => '',
]);
```

Once created, custom permissions are assigned and checked exactly like route-based permissions:

```php
$role->assignPermission('export-reports');
$user->grantPermission('export-reports');

$user->hasPermission('export-reports'); // true
$user->can('export-reports');           // true via Gate::before()

$role->assignWildcard('reporting.*');   // covers all reporting.* permissions
$role->assignWildcard('*');             // covers everything including custom permissions
```

`jaga:sync` will never soft-delete a custom permission, and `jaga:clean` will never force-delete one — even if it is soft-deleted. They are permanently protected by the `is_custom` flag.

### Wildcard Permissions

Roles and individual users can hold exact permissions (`posts.update`) or wildcard permissions (`posts.*`, `*`). Wildcards are resolved at check time.

**Resolution order:**
1. Exact match — `posts.update`
2. Resource wildcard — `posts.*`
3. Global wildcard — `*`

### Opt-In Middleware

Only routes inside a `jaga` middleware group are protected. All other routes remain publicly accessible regardless of role.

---

## Traits

### `HasRoles` — on authenticatable models

| Method | Description |
|--------|-------------|
| `assignRole(string\|int\|Role\|array $role)` | Assign one or more roles by slug, ID, model, or array of any |
| `removeRole(string\|int\|Role\|array $role)` | Remove one or more roles |
| `grantPermission(string\|int\|Permission $perm)` | Grant a direct exact permission |
| `revokePermission(string\|int\|Permission $perm)` | Revoke a direct exact permission |
| `grantWildcard(string $pattern)` | Grant a direct wildcard (e.g. `posts.*`) |
| `revokeWildcard(string $pattern)` | Revoke a wildcard |
| `hasPermission(string $routeName)` | Authoritative access check (uses cache) |
| `roles()` | Eloquent `MorphToMany` relationship |
| `permissions()` | Returns exact `Permission` models for display only — **not for access checks** |

### `HasOwnership` — on resource models

Add to any Eloquent model that needs ownership enforcement. The `jaga` middleware will automatically check that the authenticated user owns the resource.

```php
// Default: owner_key=user_id, owner_model=config('jaga.ownership.owner_model')
class Post extends Model
{
    use HasOwnership;
}

// Custom owner key and model
class Article extends Model
{
    use HasOwnership;
    protected string $ownerModel = Author::class;
    protected string $ownerKey = 'author_id';
}

// Opt out of ownership check while still using the trait
class TeamPost extends Model
{
    use HasOwnership;
    protected bool $ownershipRequired = false;
}
```

On routes with multiple bound models (e.g. `{team}/{post}`), ownership is checked on **every** model where `$ownershipRequired = true`. All checks must pass (AND logic).

---

## Middleware

The `jaga` middleware alias is registered automatically by the service provider.

```php
// Works with any guard
Route::middleware(['auth:sanctum', 'jaga'])->group(...);
Route::middleware(['auth:api', 'jaga'])->group(...);
Route::middleware(['auth', 'jaga'])->group(...);  // uses web guard
```

**Flow:**

```
Request arrives
  → Resolve guard from auth middleware (e.g. auth:sanctum → sanctum)
  → Not authenticated? → 401
  → Route has no name? → allow (unnamed routes are never restricted)
  → Jaga::policy registered for this route? → run callback → deny if false, allow if true
  → Check permission (direct grants, then roles, exact then wildcard)
  → No match? → 403
  → Has route model parameters with HasOwnership?
      → Wrong owner? → 403
  → Allow
```

---

## Custom Route Policies

Register a callback for a named route to completely replace the built-in permission and ownership checks. If a policy is registered for a route, it is the sole gate — role/permission and ownership checks are skipped.

```php
// AppServiceProvider::boot()

// Only allow the post's author to view it
Jaga::policy('posts.show', function ($user, $request) {
    $post = $request->route('post'); // requires implicit model binding
    return $post->user_id === $user->id;
});

// Allow admin users through regardless of ownership
Jaga::policy('posts.edit', function ($user, $request) {
    return $user->hasRole('admin') || $request->route('post')->user_id === $user->id;
});

// Register the same policy for multiple routes at once
Jaga::policy(['posts.update', 'posts.destroy'], function ($user, $request) {
    return $request->route('post')->user_id === $user->id;
});
```

The callback receives `($user, $request)` and must return `bool`. It runs inside the `jaga` middleware after authentication but before any permission or ownership check.

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `jaga:sync` | Sync named routes → permissions table, flush all caches |
| `jaga:define` | Create or update a custom permission not tied to any route |
| `jaga:cache` | Pre-warm the `jaga.permissions` list cache |
| `jaga:clear` | Flush all jaga caches |
| `jaga:clean` | Force-delete soft-deleted route-based permissions and orphaned pivot rows |

**Recommended deployment workflow:**

```bash
php artisan jaga:sync    # sync new/changed routes, soft-delete removed ones
php artisan jaga:cache   # warm the permissions cache
php artisan jaga:clean   # (optional) permanently remove stale route-based permissions after review
```

---

## Auto-Generated Descriptions

`jaga:sync` generates human-readable descriptions for standard RESTful route names:

| Route name | Description |
|------------|-------------|
| `posts.index` | List all posts |
| `posts.show` | View a post |
| `posts.store` | Create a post |
| `posts.update` | Update a post |
| `posts.destroy` | Delete a post |
| `admin.users.index` | List all users |

Descriptions are only overwritten if `is_auto_description` is `true`. Once you manually edit a description in the database and set `is_auto_description = false`, `jaga:sync` will never touch it again.

---

## Caching

Jaga caches resolved permissions per user. Cache tags are used when available (Redis, Memcached); a key-index fallback is used for non-tagged drivers (file, database, array).

| Key | Contents |
|-----|----------|
| `jaga.permissions` | Full permission collection |
| `jaga.user.{type}.{id}.permissions` | Resolved permissions for one model |

Default TTL: 3600 seconds (configurable).

**In tests**, use the provided trait to flush caches between test cases:

```php
use Laraditz\Jaga\Testing\RefreshJagaCache;

class MyTest extends TestCase
{
    use RefreshJagaCache;
}
```

---

## Configuration

```php
// config/jaga.php
return [
    // Default guard when no auth middleware is present on the route
    'guard' => 'web',

    'cache' => [
        'enabled'    => true,
        'ttl'        => 3600,
        'key_prefix' => 'jaga',
    ],

    'sync' => [
        // URI prefixes to exclude from jaga:sync
        'exclude_uri_prefixes'  => ['telescope', '_debugbar', 'horizon'],
        // Route name prefixes to exclude from jaga:sync
        'exclude_name_prefixes' => ['telescope.', 'debugbar.', 'horizon.'],
    ],

    'ownership' => [
        // Default foreign key checked by HasOwnership middleware
        'owner_key'   => 'user_id',
        // Default authenticatable model type for ownership comparison
        'owner_model' => \App\Models\User::class,
    ],

    'tables' => [
        'roles'            => 'roles',
        'permissions'      => 'permissions',
        'model_role'       => 'model_role',
        'role_permission'  => 'role_permission',
        'model_permission' => 'model_permission',
    ],
];
```

---

## Blade Directives

Jaga registers two Blade directives for use in templates.

### `@role` / `@endrole`

Show content only to users with a specific role. Accepts a slug, ID, model, or array (any match).

By default checks the authenticated user. Pass a `$record` as the second argument to check against any model that uses `HasRoles`.

```blade
{{-- Authenticated user --}}
@role('editor')
    <button>Edit post</button>
@endrole

{{-- Explicit record --}}
@role('editor', $admin)
    <button>Edit post</button>
@endrole

{{-- With else --}}
@role('admin')
    <a href="/admin">Admin panel</a>
@else
    <p>Access restricted.</p>
@endrole

{{-- Any of multiple roles --}}
@role(['editor', 'moderator'])
    <button>Moderate</button>
@endrole
```

### `@permission` / `@endpermission`

Show content only to records that have access to a specific route permission.

By default checks the authenticated user. Pass a `$record` as the second argument to check against any model that uses `HasRoles`.

```blade
{{-- Authenticated user --}}
@permission('posts.index')
    <a href="{{ route('posts.index') }}">View all posts</a>
@endpermission

{{-- Explicit record --}}
@permission('posts.index', $admin)
    <a href="{{ route('posts.index') }}">View all posts</a>
@endpermission

{{-- With else --}}
@permission('posts.store')
    <a href="{{ route('posts.store') }}">Create post</a>
@else
    <p>You don't have permission to create posts.</p>
@endpermission
```

Both directives silently hide content when the subject is `null`. Both also support an `@elserole` / `@elsepermission` variant for chaining conditional checks.

---

---

## Optional: Laravel Gate & Policy Integration

Jaga is self-contained — you do not need Gate or Policies to manage permissions. Everything covered above works purely through Jaga's own APIs.

If your team already uses Laravel's Gate or Policies and wants unified behaviour, Jaga integrates cleanly with both.

### Gate Integration

Jaga automatically hooks into Laravel's Gate via `Gate::before()`. This means Jaga permissions work anywhere Gate is used with no extra setup.

```php
// $user->can() / $user->cannot()
$user->can('posts.index');   // true if user has the permission via Jaga
$user->cannot('posts.show'); // true if user lacks the permission

// Gate facade
Gate::allows('posts.store');
Gate::denies('posts.destroy');

// Controller authorize()
$this->authorize('posts.update'); // throws AuthorizationException if denied

// Blade @can / @cannot
@can('posts.store')
    <a href="{{ route('posts.create') }}">New post</a>
@endcan

// Works for custom permissions too
Gate::allows('export-reports'); // true if user has the custom permission
```

**How it works:** Jaga registers a `Gate::before()` hook that returns `true` when the user has the Jaga permission (exact, wildcard, or via role), and `null` otherwise. Returning `null` means Jaga steps aside — any `Gate::define()` or Policy you register will still run normally for that ability.

This means Jaga and Laravel Policies coexist cleanly:
- Jaga grants access → Gate short-circuits with `true`
- Jaga denies → Gate falls through to your `Gate::define()` or Policy
- Non-Jaga abilities (e.g. `update-settings`) are completely unaffected

**Custom Jaga policies** (registered via `Jaga::policy()`) are route/request-level concerns and run inside the `jaga` middleware — they are not reflected in `Gate::allows()` checks.

### Policy Integration

If you register a Laravel Policy for a model that appears as a route parameter, the `jaga` middleware automatically invokes the appropriate Policy method — no extra wiring needed.

```php
// App\Policies\PostPolicy
class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    // Custom methods are picked up automatically if they match the route action name
    public function publish(User $user, Post $post): bool
    {
        return $user->id === $post->user_id && $post->isDraft();
    }
}
```

Register the Policy as normal in `AuthServiceProvider` (or `AppServiceProvider`):

```php
Gate::policy(Post::class, PostPolicy::class);
```

That's all. The `jaga` middleware will call the right Policy method for each request:

| Route name | Policy method called |
|------------|----------------------|
| `posts.show` | `view($user, $post)` |
| `posts.edit` | `update($user, $post)` |
| `posts.update` | `update($user, $post)` |
| `posts.destroy` | `delete($user, $post)` |
| `posts.restore` | `restore($user, $post)` |
| `posts.publish` | `publish($user, $post)` *(custom — matched by method name)* |
| `posts.stats` | *(no match — check skipped)* |

**Policy takes precedence over `HasOwnership`.** If a Policy is registered for a model, Jaga uses it and ignores `HasOwnership`. If no Policy is registered, Jaga falls back to `HasOwnership`.

**Policy `before()` works as expected.** If your Policy defines a `before()` method (e.g., to grant superadmins unrestricted access), it is invoked automatically via Gate and will short-circuit the model-level check.

---

## What Jaga Is Not

- Not a UI for managing roles and permissions (that's your app's responsibility)
- Not an OAuth or token-based auth system (use Sanctum or Passport)
- Not a replacement for Laravel Policies for complex business-logic authorization
- Not a solution for field-level or attribute-level access control

## License

MIT
