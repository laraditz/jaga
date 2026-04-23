# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.2] - 2026-04-23

### Added

- `DescriptionGenerator` — added `view` and `create` action templates (`"View a :resource"` and `"Create a :resource"` respectively)

### Changed

- `DescriptionGenerator` — `show` description changed from `"View a :resource"` to `"Display a :resource"`; `store` description changed from `"Create a :resource"` to `"Add a :resource"`

## [1.1.1] - 2026-04-18

### Added

- `SyncPermissionsJob` — extracted sync logic from `jaga:sync` into a dispatchable Laravel job; supports `SyncPermissionsJob::dispatch()` (queued) and `SyncPermissionsJob::dispatchSync()` (inline) from anywhere in the application
- `PermissionsSynced` event — fired after every sync with `$newCount`, `$updatedCount`, `$deprecatedCount`, and `$collisions` properties

### Changed

- `jaga:sync` command now delegates all work to `SyncPermissionsJob` and renders output by listening to the `PermissionsSynced` event; behaviour is unchanged

## [1.1.0] - 2026-04-16

### Added

- `access_level` column on `permissions` table — replaces `is_public` boolean with a three-state enum: `restricted` (default, explicit permission required), `auth` (any authenticated user), `public` (no auth required)
- `AccessLevel` PHP enum (`Laraditz\Jaga\Enums\AccessLevel`) backing the column with cases `Restricted`, `Auth`, and `Public`
- `jaga:sync` now auto-detects `access_level` from route middleware: routes with `auth` middleware → `restricted`, routes without → `public`; existing DB value is preserved on re-sync unless a config override is set
- `jaga:define --public` now sets `access_level = public` instead of the removed `is_public` flag
- Config override support for `access_level` per route via `jaga.permissions.{name}.access_level`
- `PermissionObserver` — automatically flushes all Jaga caches whenever a `Permission` record is created, updated, soft-deleted, restored, or force-deleted through any path (admin UI, Tinker, seeder, commands); pivot cleanup on delete is also handled by the observer
- `jaga:seeder` command — exports current roles, permissions, and role-permission assignments to a self-contained PHP seeder file; supports `--force` to overwrite and a configurable output path via `jaga.seeder.path`
- `jaga.access_levels` cache key — map of `name → access_level` used by the middleware on every request

### Changed

- `JagaMiddleware` access check order updated: `public` bypass → 401 → `auth` bypass → custom policy → permission check → ownership
- `CacheManager::rememberPublicRoutes()` renamed to `rememberAccessLevels()`; `flushPublicRoutes()` renamed to `flushAccessLevels()`; cache key renamed from `jaga.public_routes` to `jaga.access_levels`
- `Permission::booted()` pivot-cleanup hook moved into `PermissionObserver::deleting()`; the `booted()` method has been removed from the model

### Removed

- `is_public` boolean column on `permissions` — replaced by `access_level`

## [1.0.0] - 2026-04-15

### Added

- Initial release of `laraditz/jaga` — route-based RBAC for Laravel with auto-synced permissions and wildcard support
- `Permission` model with `name`, `description`, `group`, `is_custom`, and `is_public` columns
- Migrations for `permissions`, `roles`, `model_role`, `role_permission`, and `model_permission` tables
- `jaga:sync` command — syncs route-based permissions, auto-sets group, protects custom permissions from overwrite, and warns on collisions
- `jaga:clean` command — removes stale route permissions while preserving custom permissions
- `jaga:cache` command — caches permissions and pre-warms the public routes cache
- `jaga:define` command — creates custom permissions with optional `--public` flag
- `JagaMiddleware` — short-circuits access check for public routes, performs ownership-aware authorization
- `Jaga` facade with `ownershipPolicy()` for registering two-level ownership lookup logic
- `HasOwnership` trait with `checkOwnership()` for model-level ownership verification
- `CacheManager` for managing permission and public routes cache
- `DescriptionGenerator` — auto-generates human-readable descriptions and groups from route names; supports `edit` action; config-overridable per route
- `permissions` config key for locking specific route descriptions and groups
- Gate/Policy test helpers for asserting permission behavior in tests
- Published migrations use real timestamps via `publishesMigrations()`
