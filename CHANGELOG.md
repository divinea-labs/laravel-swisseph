# Changelog

All notable changes to `laravel-swisseph` will be documented in this file.

## v0.1.2 - 2025-12-26

Improved developer experience by adding method annotations to the facade, enabling better IDE autocompletion and static analysis.
No runtime behavior changes.

## v0.1.1 - 2025-12-24

v0.1.1 — Dependency Resolution Fix (PHP 8.4)

Enforced phpdocumentor/reflection >= 6.1 to prevent installation issues on PHP 8.4
Improved dependency resolution stability in projects with existing composer.lock

## v0.1.0 - 2025-12-23

Initial public release.

- Laravel 11.x and 12.x support
- PHP 8.3 and 8.4 compatibility
- Deterministic Swiss Ephemeris (swetest) CLI wrapper
- Explicit configuration and stable DTO output
