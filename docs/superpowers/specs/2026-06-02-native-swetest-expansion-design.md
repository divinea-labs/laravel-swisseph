# Native swetest expansion — Design (SP0 Foundation + SP1 Eclipses)

> **Status:** approved design, pre-implementation.
> **Date:** 2026-06-02
> **Branch:** `feature/native-swetest-expansion` (laravel-swisseph)
> **Scope of THIS spec:** SP0 (API restructure) + SP1 (eclipses). Later sub-projects are listed for context only and get their own specs.
> **Constraint:** `laravel-swisseph` is a public package — keep it tidy; no commits without maintainer review.

---

## 1. Background & motivation

`laravel-swisseph` wraps the `swetest` CLI but currently realizes only a fraction of its capability: single-instant positions (+ houses) and a rise/set pipeline. The whole "special events" family (eclipses, occultations, heliacal phenomena, meridian transit, orbital elements) and batch time-series (`-n`/`-s`) are unmapped.

Driver: **stop approximating in PHP what swetest computes exactly natively.** Concretely, `astro-core`'s `Eclipses` domain (`FindEclipsesAction`) is a *node-proximity heuristic* — it only computes Moon↔node distance, with no magnitude, Saros, contact times, or local visibility. swetest's native `-solecl`/`-lunecl` returns all of that exactly.

This is a multi-package program: each feature slice has a `laravel-swisseph` side (expose the native function) and an `astro-core` side (consume it, replacing PHP approximations).

## 2. Program decomposition (roadmap — context only)

Each sub-project = its own spec → plan → implementation cycle.

| # | Sub-project | laravel-swisseph | astro-core | Priority |
|---|---|---|---|---|
| **SP0** | API restructure into sub-builders | split `Swisseph` god-class into `positions()`/`risings()` + shared kernel | update 3 call sites | foundation |
| **SP1** | Eclipses `-solecl`/`-lunecl` | new `eclipses()` pipeline | replace node-proximity heuristic | 🔴 highest |
| SP2 | Occultations `-occult` | new `occultations()` | new capability | 🔴 |
| SP3 | Bodies & data: fixed stars `-xf`, MPC asteroids `-xs`, moons `-xv`, output codes (sidereal time, Delta T, obliquity, ayanamsha) | extend `positions()` | feed FixedStars domain real positions | 🟠 |
| SP4 | Batch ephemeris `-n`/`-s` | multi-frame output + parser | add `bodiesOver()` to `EphemerisProvider`, route scan through it | 🟠 perf (~20-30×) |
| SP5 | Heliacal events `-hev` (models already exist in rise builder) | finish as `heliacal()` | new capability | 🟡 |
| SP6 | Meridian transit `-metr` | fold into `risings()` | new capability | 🟡 |
| SP7 | Orbital elements `-orbel` | new output mode | new capability | 🟡 |
| SP8 | Relative ephemeris: midpoints `-D` + differential `-d` | modifiers on `positions()` | optional (astro-core has PHP equivalents) | 🟡 |

**SP8 rationale (maintainer override of earlier YAGNI):** `laravel-swisseph` is a standalone public package — it should expose swetest's full native capability regardless of whether a given consumer also uses astro-core (not everyone will). `-d` (longitude/latitude difference between a reference body and each body in the `-p` list) and `-D` (midpoint, same mechanism) are literally the same swetest code path toggled by flag, so they ship together as one slice. astro-core keeps its own PHP midpoint math; this just makes the package complete.

**Still dropped (YAGNI):** cosmetic formatting flags (`-roundsec`, `-dms`, `-hor`) — consumers use decimals. ET/Julian-Day input, LMT/LAT output, `-cob`, `-jplhor` folded opportunistically into SP0/SP3, not standalone.

---

## 3. SP0 — Foundation: shared kernel + sub-builders

### 3.1 Problem

`src/Swisseph.php` (392 lines, 25 public methods) already mixes two pipelines (positions + rise/set). Adding 5 more pipelines would make it a god object. Package is pre-1.0 (v0.2.x); the only consumer is `astro-core` (3 call sites) — breaking changes are acceptable and controlled.

### 3.2 Target shape

The `Swisseph` facade becomes a thin factory returning dedicated builders:

```php
Swisseph::positions()   // → PositionsBuilder   (today's SwissephCommandBuilder, renamed)
Swisseph::risings()     // → RisingsBuilder      (today's RiseCommandBuilder, renamed)
Swisseph::eclipses()    // → EclipsesBuilder     (new, SP1)
// SP2-SP7 add: occultations(), heliacal(), orbitalElements(), …
```

### 3.3 Shared kernel

Extract the common concerns every builder needs into a reusable base:

- **`Support/Concerns/ResolvesSwissephEnvironment`** (trait): config load (`executable`, `ephemeris_dir`), path normalization, `EphOptions` collection + de-dupe + sorted emission, and shared date/time/location state + their argument builders (`b<d.m.Y>`, `ut<H:i:s>`, geopos formatting via the existing `fmt()` helper).
- **`Support/Command/SwissephExecutor`** (exists): unchanged execution via Symfony Process; **enhanced** to strip swetest's leading command-echo line and known header lines (`geo. long …`) before handing stdout to a parser. Each pipeline passes its own line-filter predicate.
- **`Data/SwissephCommand`** (exists): unchanged.

### 3.4 Per-pipeline unit shape (the convention every pipeline follows)

```
<Pipeline>/
├── <Pipeline>Builder.php   # fluent, immutable-style; uses ResolvesSwissephEnvironment; build(): SwissephCommand
├── <Pipeline>Parser.php    # stdout → list<…Data>; block-aware where needed
├── Data/…Data.php          # final readonly, JsonSerializable (spatie/laravel-data)
└── Enums/…                 # string/int-backed enums specific to the pipeline
```

Each unit has one clear purpose, communicates via `SwissephCommand` / typed DTOs, and is testable in isolation (build args without a binary; parse fixtures without a binary).

### 3.5 Migration

- `SwissephCommandBuilder` → `PositionsBuilder`; `RiseCommandBuilder` → `RisingsBuilder` (move under `Support/Positions/` and `Support/Rising/` respectively for symmetry). Public behavior preserved; only the entry path changes (`Swisseph::positions()->…`).
- Update `astro-core` 3 call sites:
  - `Domains/Events/Providers/SwissephEphemerisProvider.php`
  - `Domains/PlanetaryTime/Providers/SwissEphSolarTimesProvider.php`
  - `Domains/Returns/Support/ReturnChartBuilder.php`
  These swap `Swisseph::setLocation(...)` → `Swisseph::positions()->setLocation(...)` and rise/set calls → `Swisseph::risings()->…`.
- No backward-compat shim (maintainer chose the clean restructure). README + CHANGELOG updated to document the new entry points (breaking change, noted for the 0.3.0 line).

### 3.6 Out of scope for SP0

No new astronomical features. SP0 is purely structural + the executor line-filter enhancement that SP1 depends on.

---

## 4. SP1 — Eclipses

### 4.1 Real swetest output (parsing reference — captured on the compiled binary)

swetest echoes the command line as the **first stdout line** and, for `-local`, a `geo. long …` header line. Both must be skipped. Records are multi-line; layout differs by (solar|lunar) × (global|local). Tabs and spaces are mixed → tokenize on `\s+`.

**Solar, global (`-solecl -n2`)** — 3 lines per event:
```
annular solar	17.02.2026	  12:11:51.1	131.068478 km	0.9638/0.9797/0.9288	saros 121/61	2461089.008230
	  09:56:44.8    11:43:01.9    12:41:02.0    14:27:37.3 dt=71.1
	  87° 4' 6"	 -64°41' 2"	2 min 19.42 sec
```
- L1: `<type> solar` · date · max-time(UT) · core-shadow-km · `m1/m2/m3` · `saros <ser>/<num>` · JD
- L2: 4 contact times (exterior begin, total/annular begin, total/annular end, exterior end) · `dt=<deltaT>`
- L3: central-line lon · lat · max duration

**Solar, local (`-solecl -local -geopos…`)** — 2 lines per event (after the geo header):
```
partial 12.08.2026	  18:03:06.0	0.8511/0.8511/0.8190	saros 126/48	2461265.252152
	0 min 0.00 sec	  17:14:40.3     -            -            -         dt=71.3
```
- L1: `<type>` · date · max-time(UT) · `m1/m2/m3` · saros · JD  *(no core-shadow km)*
- L2: duration · 4 local contacts (`-` where not visible) · `dt=<deltaT>`

**Lunar, global (`-lunecl -n2`)** — 3 lines per event:
```
total lunar eclipse	 3.03.2026	  11:33:39.0	1.1507/2.1839	saros 133/27	2461102.981702
    08:44:21.8    09:50:02.9    11:04:30.1    12:02:49.3    13:17:15.5    14:23:05.3 dt=71.1
	-170°36'44"	   6°24' 6"
```
- L1: `<type> lunar eclipse` · date · max-time(UT) · `umbral-mag/penumbral-mag` · saros · JD
- L2: **6** contact times (penumbral begin, partial begin, total begin, total end, partial end, penumbral end) · `dt=<deltaT>` (`-` where absent, e.g. partial eclipse has no total phase)
- L3: location lon · lat (sublunar point)

### 4.2 Builder API

```php
Swisseph::eclipses()->solar()->global()->from('2026-01-01')->count(5)->onlyTotal()->get();
Swisseph::eclipses()->solar()->local($geoLocation)->from('2026-01-01')->count(5)->get();
Swisseph::eclipses()->lunar()->global()->from('2026-01-01')->count(5)->backward()->get();
```

Fluent methods:
- Kind: `solar()` / `lunar()` (required; throw if missing at `get()`).
- Scope: `global()` (default) / `local(GeoLocation $geo)` — local emits `-local -geopos<lon>,<lat>,<elev>`.
- Window: `from(Carbon|string)` → `-b<d.m.Y>`; `count(int)` → `-n<count>` (default 1); `backward()` → `-bwd`.
- Type filters (compose to swetest flags): `onlyTotal()` `-total`, `onlyPartial()` `-partial`, `onlyAnnular()` `-annular`, `onlyHybrid()` `-anntot`, `onlyPenumbral()` `-penumbral` (lunar), `onlyCentral()`/`onlyNonCentral()` `-central`/`-noncentral` (solar global). Invalid kind×filter combos (e.g. `onlyAnnular()` on lunar) throw a domain exception at `get()`.
- `withEphOptions(...)` available via the shared trait (ephemeris source propagates).

Terminal: `get(): EclipseCollection` (a thin `list<EclipseEventData>` wrapper with helpers like `solarOnly()`, `first()`).

### 4.3 Unified DTO (one type with named value-objects — decided)

```
EclipseEventData (final readonly, JsonSerializable)
├── kind: EclipseKind            // SOLAR | LUNAR
├── type: EclipseType            // TOTAL | ANNULAR | PARTIAL | HYBRID | PENUMBRAL
├── scope: EclipseScope          // GLOBAL | LOCAL
├── maxAt: Carbon (UTC)              // package convention is Carbon (cf. RiseSetEvent), not CarbonImmutable
├── julianDay: float
├── deltaT: float                // seconds
├── magnitudes: EclipseMagnitudes   // solar: 3 (e.g. ratio/umbral/penumbral); lunar: 2 (umbral/penumbral)
├── saros: SarosSeries              // series:int, member:int
├── contacts: EclipseContacts       // 6 named nullable phases (below)
├── location: ?EclipseLocation      // lon/lat (central line / sublunar point)
├── coreShadowKm: ?float            // solar global only
└── duration: ?float                // seconds; solar only (central/local max duration)
```

Value objects:
- **`EclipseMagnitudes`**: `primary`, `secondary`, `tertiary` (?float). Solar fills 3, lunar fills 2 (`tertiary` null). Named so the difference hides behind accessors.
- **`SarosSeries`**: `series:int`, `member:int`.
- **`EclipseContacts`**: `penumbralStart`, `partialStart`, `totalStart`, `totalEnd`, `partialEnd`, `penumbralEnd` — each `?Carbon` (UTC). Solar maps its 4 contacts onto `partialStart/totalStart/totalEnd/partialEnd` (penumbral slots null); lunar uses all 6; local/partial eclipses leave inapplicable slots null (the `-` placeholders).
- **`EclipseLocation`**: `longitude:float`, `latitude:float`.

Convenience accessors on `EclipseEventData`: `isSolar()`, `isLunar()`, `isTotal()`, `isLocal()`.

Enums: `EclipseKind`, `EclipseType`, `EclipseScope` (all string-backed).

**Rationale for one type:** matches how `astro-core` already models eclipses (single `EclipseEventData[]` stream discriminated by enum) → no union types at the consumer; eclipses are consumed polymorphically (timeline/window/near-date) so a single type sorts/filters/serializes uniformly; the genuine differences are few and naturally nullable, hidden behind named value-objects (not a flat null bag). Reverse migration (two types → one stream) is harder, so one type is the safer default.

### 4.4 Parser

`EclipseParser` is **block-aware**: it skips the echo + `geo. long` lines, then groups remaining lines into records (3 lines solar-global / 2 lines solar-local / 3 lines lunar-global; a record starts at a line whose first token is a known type word). It branches on detected kind+scope to map tokens. Helpers:
- date+time tokens → `Carbon` UTC (formats: `d.m.Y` + `H:i:s.u`, matching existing `RiseParser`).
- `m1/m2/m3` split on `/` → `EclipseMagnitudes`.
- `saros <ser>/<num>` → `SarosSeries`.
- `dt=<x>` → `deltaT`.
- `<deg>°<min>'<sec>"` → decimal degrees for `EclipseLocation`.
- `<n> min <s> sec` → seconds for `duration`.
- `-` placeholders → null contact/field.

Behavior: no events in window → empty collection (never throw). Unexpected line shape → log + skip that record, don't crash (mirrors `RiseParser` tolerance).

### 4.5 astro-core integration boundary (defined here, implemented in astro-core's own spec)

- New `SwissephEclipseProvider` (in astro-core) implementing an `EclipseProvider` contract; `EclipsesQuery::within($from,$to)` delegates to `Swisseph::eclipses()` instead of `FindEclipsesAction`'s node-proximity heuristic.
- astro-core's `EclipseEventData` gets enriched (magnitude, saros, type, contacts) — that DTO change + the existing `EphemerisEclipseProvider` used by Elections are an astro-core-side concern, tracked in the astro-core spec. SP1 only guarantees the data is available and typed.

---

## 5. Error handling (both SPs)

- Missing required builder input (e.g. eclipse kind) → typed exception at `get()` (new `Exceptions/` per pipeline, following existing `Invalid…Exception` style).
- Invalid filter×kind combination → typed exception at `get()`.
- swetest non-zero exit / timeout → surfaced by `SwissephExecutor` (existing behavior; respects `config('swisseph.timeout')`).
- Empty/zero results → `[]` / empty collection, never an exception.
- Malformed line → log + skip, continue.

## 6. Testing strategy

Mirror existing test structure (`tests/Features/Support/...`), Pest:
- **`EclipsesBuilderTest`** — assert generated argument arrays for every combination: solar/lunar × global/local × each type filter × `backward()` × `count()`/`from()` × eph-option propagation. No binary needed.
- **`EclipseParserTest`** — fixtures from the **real captured output** in §4.1 (solar global, solar local Warsaw, lunar global; include a partial-lunar with `-` placeholders). Assert field-by-field mapping incl. nullable contacts, magnitudes, saros, location, duration, deltaT.
- **SP0 regression** — existing `SwissephCommandBuilderTest` / parser tests migrate to the renamed `PositionsBuilder` and keep passing; rise/set tests unchanged behaviorally.
- **Integration** (with binary) — self-skips when `swetest` absent (same pattern as astro-core integration tests).
- Green gate before review: full Pest pass + PHPStan 0 errors.

## 7. Deliverables for SP0+SP1

- SP0: shared kernel (`ResolvesSwissephEnvironment` trait), executor line-filter enhancement, `PositionsBuilder`/`RisingsBuilder` rename + `Swisseph` factory entry points, astro-core 3 call-site updates, README/CHANGELOG.
- SP1: `EclipsesBuilder`, `EclipseParser`, `EclipseEventData` + value objects, `EclipseKind`/`EclipseType`/`EclipseScope` enums, exceptions, tests with real-output fixtures.

## 8. Open items / explicitly deferred

- Exact semantic labels of the 3 solar magnitude values (swetest doc: they are three magnitude definitions) — confirm naming during implementation; `EclipseMagnitudes.primary/secondary/tertiary` keeps it safe meanwhile.
- astro-core DTO enrichment + Elections `EphemerisEclipseProvider` swap → astro-core spec (SP1 consumer side).
- SP2–SP7 → own specs.
```
