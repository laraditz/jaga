# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
