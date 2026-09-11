# Changelog

All notable changes to `laravel-swisseph` will be documented in this file.

## v0.3.5 — House identity - 2026-09-11

### Why

Swiss Ephemeris writes the twelve house cusps and eight special points in one column, numbered 1–20, and `House` is backed by that raw number. So `13` is the Ascendant, not a thirteenth house, and nothing in the enum said so. Every consumer had to guess what a row was. At least one guessed wrong: it expected cusp rows named `'1'`…`'12'`, while the parser names them `'House 1'`…`'House 12'`. It read an empty cusp list for every chart, silently, and its tests stayed green because they were fed rows built on the same wrong assumption.

### What's new

**`House::cuspNumber(): ?int`** — the house number of the twelve cusps; `null` for the eight points:

```php
House::HOUSE_7->cuspNumber();    // 7
House::ASCENDANT->cuspNumber();  // null

```
**`House::slug(): string`** — a wire-safe identifier for all 20 cases. Cusps are `house_1`…`house_12`. The points are `ascendant`, `midheaven`, `armc`, `vertex`, `equatorial_ascendant`, `co_ascendant_koch`, `co_ascendant_munkasey` and `polar_ascendant`. Every slug matches `^[a-z][a-z0-9_]*$` and is unique across the enum.

**`House::fromName(string $name): ?self`** — the inverse of `getName()`. Returns `null` for a name that belongs to no house or point. `fromName('1')` is `null`, because the parser never produces that name.

**`HouseData::house(): House`** — the enum case behind a parsed row, which is how you reach the three methods above from a frame:

```php
foreach ($frame->houses as $row) {
    $cusp = $row['house']->house()->cuspNumber(); // 1..12, or null for a point
}

```
### Fixed

Three display names carried a stray double quote: `CO-Ascendant" (W. Koch)`, `CO-Ascendant" (M. Munkasey)`, `Polar Ascendant" (M. Munkasey)`. They now read `CO-Ascendant (W. Koch)`, `CO-Ascendant (M. Munkasey)` and `Polar Ascendant (M. Munkasey)`. If you compare against the old strings, update them, or better, compare enum cases or slugs.

## v0.3.4 — PlanetBody slugs - 2026-09-08

Additive release. Drop-in upgrade — `^0.3.0` already covers it, so no constraint needs to move.

### Why

`PlanetBody` is backed by the raw Swiss Ephemeris integer, and `getName()` returns a label written for people: `Mean Apogee (Lilith)`, `Neptune (Leverrier)`, `Selena / White Moon`. There was no stable string identifier in between, so every consumer serializing a body to JSON had to invent one. At least one of them didn't, and published `"Mean Apogee (Lilith)"` as a JSON object key — a key with a space and parentheses in it, which no TypeScript consumer can express without quoting the whole thing.

### What's new

**`PlanetBody::slug(): string`** — a wire-safe identifier for all 43 cases:

```php
PlanetBody::SUN->slug();        // 'sun'
PlanetBody::MEAN_APOG->slug();  // 'mean_apogee'
PlanetBody::TRUE_NODE->slug();  // 'true_node'

Every slug matches ^[a-z][a-z0-9_]*$ and is unique across the enum. Case-name abbreviations are expanded rather than carried over — MEAN_APOG becomes mean_apogee and INTP_PERG becomes interpolated_perigee — because a slug is a permanent public contract and an abbreviation in one is a wart you cannot remove later.

PlanetBody::fromName(string $name): ?self — the inverse of getName(), for callers that hold a label and need the case back. Returns null for a name belonging to no body, so an unknown label is something you can detect rather than something that silently becomes a wrong body.


```
## Restore phpdocumentor/reflection 6.x compatibility - 2026-09-06

Dependency fix. phpdocumentor/reflection was tightened to ^7.0 in #6, which blocked apps on the 6.x line from installing v0.3.1 and v0.3.2 — including the rise/set parser fix — with composer silently declining the upgrade rather than reporting the conflict. The constraint is ^6.1 || ^7.0 again, preserving the PHP 8.4 floor. No API change.

## v0.3.2 - Rise/set single-digit day fix - 2026-09-06

Bugfix release. RiseParser silently discarded rise/set lines for days 1–9 of every month, because swetest space-pads the day-of-month (6.09.2026) while the parser's guard required two digits. Strict-mode callers saw RiseSetNotFoundException on roughly a third of all dates. No API change; upgrading is a drop-in.

## v0.3.1 — FixedStar & Asteroid convenience enums - 2026-06-14

Description:
Two new enums make working with fixed stars and asteroids easier — readable,
auto-completable constants instead of "magic" strings and catalog numbers.
Fully additive and backward-compatible release.

### Added

- **`FixedStar` enum** (string-backed, 44 curated stars) — e.g.
  `FixedStar::SIRIUS`, `FixedStar::VEGA`, `FixedStar::ARCTURUS`. Values map to the
  Swiss Ephemeris catalog names (e.g. `RIGIL_KENTAURUS => 'Bungula'`).
- **`Asteroid` enum** (int-backed, 24 named asteroids) — e.g.
  `Asteroid::EROS` (433), `Asteroid::PSYCHE` (16), `Asteroid::LILITH` (1181).
- Selectors now accept the enum **alongside** the existing raw string/int:
  - `PositionsBuilder::selectFixedStar()` and `selectAsteroid()`
  - `OccultationsBuilder` and `HeliacalBuilder` (`forStar`)
  

### Changed

- `selectAsteroid(433)` can now be written as `selectAsteroid(Asteroid::EROS)`.
- `selectFixedStar('Sirius')` can now be written as `selectFixedStar(FixedStar::SIRIUS)`.
- Raw strings/ints still work — for stars/asteroids outside the curated list
  (the "long tail"), pass the value directly, just as before.

### Docs

- New `docs/enums.md` chapter covering both enums and raw input.
- README updated.

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
