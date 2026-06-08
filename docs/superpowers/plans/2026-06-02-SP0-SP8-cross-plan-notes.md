# Cross-plan consistency notes (SP0–SP8)

> Read this BEFORE executing any individual SP plan. The 9 plans were drafted in parallel; this file resolves the few cross-cutting decisions so they compose without conflict. Where a plan's inline text disagrees with a rule here, **this file wins**.

## 1. Build order & dependencies

```
SP0  (foundation)        ── must be first; everything depends on it
 ├─ SP1  eclipses         (independent)
 ├─ SP2  occultations     (independent)
 ├─ SP3  bodies & data    ── OWNS PlanetBodyData::$index widening + non-numeric parser branch
 │   └─ SP8 relative eph  ── DEPENDS on SP3 (composite A-B / A/B names need the same branch)
 ├─ SP4  batch -n/-s      (independent; also touches astro-core)
 ├─ SP5  heliacal         (independent)
 ├─ SP6  meridian transit (independent)
 └─ SP7  orbital elements (independent)
```

Recommended execution order: **SP0 → SP1 → SP2 → SP3 → SP8 → SP4 → SP5 → SP6 → SP7**. Only the SP3→SP8 edge is a hard ordering constraint; the rest can be reordered freely.

## 2. Shared-edit ownership (avoid double-editing the same file)

| File | Owner plan | Rule for other plans |
|---|---|---|
| `src/Data/PlanetBodyData.php` — widen `$index` from `int` to `?int` | **SP3** | SP4, SP5, SP8 must NOT re-declare. If executing SP8/SP4 before SP3, apply the one-line widening idempotently and note it; if SP3 already merged, skip. |
| `src/Support/Positions/PositionsParser.php` — non-numeric first-column branch (store `index=null, name=token`, keep raw datum, no float coercion) | **SP3** | SP8's composite `A-B`/`A/B` names rely on this exact branch. SP8 must REUSE it, not add a parallel one. If SP3 not yet merged, SP8 adds the minimal branch gated by `is_numeric()` so it merges cleanly with SP3 later. |
| `src/Swisseph.php` — add one `<pipeline>()` factory method | each pipeline plan | Each adds exactly one method; no conflict (different method names). Re-run the full suite after each. |
| `src/Facades/Swisseph.php` — add one `@method` line | each pipeline plan | Append-only; no conflict. |
| `src/SwissephServiceProvider.php` — `registeringPackage()` bindings | each pipeline plan | Append one `bind(...)` per pipeline; no conflict. |

## 3. Environment trait (SP0) — what it does and does NOT provide

`ResolvesSwissephEnvironment` provides: `$executable`, `$epheDir`, `$dateTime`, `$ephOptions`; methods `bootSwissephEnvironment()`, `setDateTime()`, `withEphOptions()`, `epheDirArg()`, `dateArg()`, `utTimeArg()`, `ephOptionArgs()`, `fmt()`, `getDate()`.

It does **NOT** provide location. Therefore:
- `PositionsBuilder` / `RisingsBuilder` keep their OWN `$longitude/$latitude/$elevation/$place` + `setLocation()/setPlace()` (positions also `getLongitude()/getLatitude()/getPlace()`).
- **Event builders (SP1 eclipses, SP2 occultations, SP5 heliacal, SP6 meridian)** declare their OWN nullable geo fields and setter, named to avoid any trait/position clash:
  - fields: `protected ?float $geoLon = null; protected ?float $geoLat = null; protected float $geoElev = 0.0;`
  - setter: `local(float $lon, float $lat, float $elev = 0.0)` (eclipses/occultations) or `at(float $lon, float $lat, float $elev = 0.0)` (heliacal/meridian).
  - "geopos required" pipelines (heliacal, meridian, eclipse-local, occultation-local) throw their typed exception at `get()` when `$geoLon === null`.
  - emit `geopos<fmt(lon)>,<fmt(lat)>,<fmt(elev)>` using the trait's `fmt()`.

This removes the property-redeclaration fatal that the parallel drafts flagged (SP2/SP5/SP6).

## 4. Executor skip-prefix convention (SP0 Task 2)

`SwissephExecutor::run(SwissephCommand $command, array $skipPrefixes = [])` **auto-skips the command-echo line** by prepending `$command->executable` to the skip list (the echo starts with the executable path; in production that path is ABSOLUTE, so a literal `'./'` prefix from the captured examples is NOT reliable and must not be relied on).

Therefore each event builder passes only its mode-specific header(s):
- local modes (eclipses/occultations/heliacal/meridian): `run($command, ['geo. long'])`.
- global eclipse/occultation: `run($command, [])` (only the auto-skipped echo).
- position/series/orbital modes: no echo is emitted by swetest in those modes, so `[]` is fine.

Any plan text that says `skipPrefixes ['./', 'geo. long']` should be read as `['geo. long']` — the `./` is redundant and prod-incorrect; the executor handles the echo.

## 5. Constants & names (verified against current source)

- `EphOptions::SWISS_TYPE = 'eswe'`, `JPL_TYPE = 'ejpl'`, `MOSHIER = 'emos'`, `TRUE_POSITIONS = 'true'`, `NO_NUTATION = 'nonut'`, `EPHEMERIS_PATH = 'edir'`.
- DTOs use `Carbon` (mutable), NOT `CarbonImmutable` — match `RiseSetEvent`.
- DTOs extend spatie `Spatie\LaravelData\Data` (not plain readonly classes) — match `RiseSetEvent`, `SwissephCommand`.
- Date arg format: `b<d.m.Y>`; UT time arg: `ut<H:i:s>`; column separator literal `PPP` via `gPPP`; header suppression `head`.
- Post-SP0 class names: `PositionsBuilder`, `PositionsParser` (in `src/Support/Positions/`), `RisingsBuilder` (in `src/Support/Rising/`). Plans for SP3/SP4/SP8 are written against these post-SP0 names.

## 6. Git discipline (all plans)

- Work on `feature/native-swetest-expansion` (laravel-swisseph) and a sibling feature branch in astro-core for SP4/SP0-Task7.
- Local commits are allowed; **do NOT push** (public package — maintainer pushes).
- Each plan's commit steps are for the executing worker; the planning phase does not run them.

## 7. Green gate (every plan, before declaring done)

`vendor/bin/pest` all-pass + `vendor/bin/phpstan analyse` 0 errors. Integration tests that need the `swetest` binary self-skip when it is absent (mirror astro-core's pattern).
