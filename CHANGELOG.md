# Changelog

All notable changes to `laravel-swisseph` will be documented in this file.

## [Unreleased] / 0.3.0

**BREAKING:** Entry points restructured into sub-builders — use `Swisseph::positions()` / `Swisseph::risings()` instead of the flat fluent API.

Migration:

```php
// Before (0.2.x)
Swisseph::setLocation(...)->setDateTime(...)->get();
Swisseph::setDateTime(...)->setLocation(...)->getSunEvents();

// After (0.3.0)
Swisseph::positions()->setLocation(...)->setDateTime(...)->get();
Swisseph::risings()->setDateTime(...)->setLocation(...)->getSunEvents();
```

Internal changes:
- Shared `ResolvesSwissephEnvironment` trait (executable/ephe-dir/date/time/eph-options).
- `SwissephCommandBuilder` renamed to `PositionsBuilder` (`src/Support/Positions/`).
- `SwissephParser` renamed to `PositionsParser` (`src/Support/Positions/`).
- `RiseCommandBuilder` renamed to `RisingsBuilder` (`src/Support/Rising/`).
- `SwissephExecutor` gains `runRaw()` + header-skip filtering for event mode output.
- `Swisseph` class is now a thin factory; terminal methods (`get()`, `getCliCommand()`, `getRiseSetEvents()`, etc.) live on the respective builder.

## v0.2.1 — Laravel 13 support - 2026-05-31

Adds official support for **Laravel 13**, alongside the existing Laravel 11 and 12.

This is a fully backward-compatible, additive release — no API changes, no removals.

### Added

- Laravel 13.x support (`illuminate/contracts` now allows `^13.0`)
- Laravel 13 to the GitHub Actions test matrix (Orchestra Testbench `^11.0`)

### Changed

- Widened `orchestra/testbench` dev requirement to `^9.0 || ^10.0 || ^11.0`

### Fixed

- Static analysis: configured Larastan `configDirectories` so `env()` calls in `config/swisseph.php` are no longer falsely flagged when
  analysing the package in isolation

**Compatibility:** PHP 8.3 / 8.4 · Laravel 11.x, 12.x, 13.x

## v0.2.0 — Rise/Set events pipeline - 2026-02-15

Adds a full rise/set calculation pipeline based on Swiss Ephemeris -rise.

Highlights:

- sunrise/sunset support
- any celestial body
- multi-body batch orchestration
- UTC + timezone filtering modes
- structured DTO output
- microsecond precision

Fully backward compatible with existing API.

## v0.2.0 - 2026-02-15

Added full rise/set events pipeline.

- sunrise / sunset support
- any celestial body
- batch orchestration
- Mode A (UTC day)
- Mode B (local calendar day)
- structured DTO output
- microsecond precision timestamps

Fully backward compatible.

## v0.1.2 - 2025-12-26

Improved developer experience by adding facade method annotations.

- Better IDE autocompletion
- Improved static analysis
- No runtime behavior changes

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
