# SP8 — Relative Ephemeris (midpoints `-D` + differential `-d`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two mutually-exclusive modifiers to the positions pipeline — `differentialTo(PlanetBody $reference)` (emits `-d<ref>`, longitude/latitude DIFFERENCE, name column `A-B`) and `midpointTo(PlanetBody $reference)` (emits `-D<ref>`, MIDPOINT, name column `A/B`) — and teach `PositionsParser` to store the composite name column as the body name with a null index.

**Architecture:** These are pure modifiers on the existing positions pipeline (post-SP0 `PositionsBuilder` / `PositionsParser`). The same swetest code path is toggled by flag (`-d` vs `-D`), so they ship together. Only ONE of the two may be active at a time: calling one clears the other (last call wins). No new DTO is introduced — results come back as the normal `AstroTimeFrame` whose `planet_bodies[]` entries carry `PlanetBodyData{ name: 'Mer-Sun', index: null }`. To allow the null index, `PlanetBodyData::$index` becomes nullable, and `PositionsParser::parsePlanetRow` is given a gated branch: if the name column does not resolve to a `PlanetBody` enum (i.e. it is a composite `A-B` / `A/B` label), the row is stored with the raw label as `name` and `index = null`.

**Tech Stack:** PHP 8.3/8.4, Laravel 11/12/13, spatie/laravel-data, spatie/laravel-package-tools, Pest 4, Symfony Process, larastan/PHPStan.

**Conventions locked by SP0 (followed here):**
- The positions pipeline lives in `src/Support/Positions/` with `PositionsBuilder.php` + `PositionsParser.php`; DTOs under `src/Data/`; enums under `src/Enums/`.
- `Swisseph::positions()` returns a fresh `PositionsBuilder` resolved from the container.
- Builders are fluent and return `static`; `build()` emits a `SwissephCommand`.

**SP0 dependency note (gating):** This plan EXTENDS the post-SP0 `PositionsBuilder` / `PositionsParser` (renamed from `SwissephCommandBuilder` / `SwissephParser`). If SP0 is not yet merged when this plan runs, apply every change in this plan to the pre-SP0 classes instead (`src/Support/Command/SwissephCommandBuilder.php` → builder methods; `src/Support/Parsing/SwissephParser.php` → parser tweak) and the corresponding pre-SP0 test directories (`tests/Features/Support/Command/`, `tests/Features/Support/Parsing/`). The logic is identical; only the class names / namespaces / file locations differ. The plan below is written against the post-SP0 names.

**SP3 dependency note (gating):** SP3 also touches non-numeric name columns (fixed stars `Sirius,alCMa`, asteroids `Eros`). The composite-label branch added here (Task 2) is written to be ADDITIVE and idempotent: it triggers only when `PlanetBody::tryFrom()` returns `null`, so it does not conflict with SP3 if SP3 lands first (SP3 would already have introduced an equivalent fallback — in that case Task 2 Step 3 is a no-op verification rather than a new edit). Either ordering is safe.

---

## File Structure

- Modify: `src/Support/Positions/PositionsBuilder.php` (post-SP0) — add `$relativeReference`, `$relativeMode`, `differentialTo()`, `midpointTo()`; emit `-d<ref>` / `-D<ref>` in `build()`.
- Modify: `src/Support/Positions/PositionsParser.php` (post-SP0) — gated composite-label branch in `parsePlanetRow()`.
- Modify: `src/Data/PlanetBodyData.php` — make `$index` nullable (`?int`).
- Create: `tests/Features/Support/Positions/PositionsRelativeTest.php` — builder flag emission, mutual-exclusivity, parser composite-label mapping (differential + midpoint fixtures), self-skipping integration test.

No new enum, DTO, exception, or parser class is required.

---

### Task 1: Builder — `differentialTo()` / `midpointTo()` with mutual exclusivity

**Files:**
- Modify: `src/Support/Positions/PositionsBuilder.php`
- Test: `tests/Features/Support/Positions/PositionsRelativeTest.php` (new file; this task adds the builder tests)

- [ ] **Step 1: Write the failing builder tests**

Create `tests/Features/Support/Positions/PositionsRelativeTest.php` with the builder-side tests. (The parser tests in Task 3 are appended to this same file.)

```php
<?php

use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;

/*
 * Differential (-d) and Midpoint (-D) flag emission
 */
it('emits -d<ref> for differentialTo()', function () {
    $builder = new PositionsBuilder;
    $builder->selectBodies(\DivineaLabs\Swisseph\Enums\PlanetBodySelection::MERCURY)
        ->differentialTo(PlanetBody::SUN);

    $cmd = $builder->build()->toCliString();

    expect($cmd)->toContain('-d0');
    expect($cmd)->not->toContain('-D');
});

it('emits -D<ref> for midpointTo()', function () {
    $builder = new PositionsBuilder;
    $builder->selectBodies(\DivineaLabs\Swisseph\Enums\PlanetBodySelection::SATURN)
        ->midpointTo(PlanetBody::CHIRON);

    $cmd = $builder->build()->toCliString();

    expect($cmd)->toContain('-D15');
    // -D15 must not be confused with a -d flag
    expect($cmd)->not->toContain('-d15');
});

it('emits no relative flag by default', function () {
    $builder = new PositionsBuilder;
    $builder->selectBodies(\DivineaLabs\Swisseph\Enums\PlanetBodySelection::SUN);

    $cmd = $builder->build()->toCliString();

    expect($cmd)->not->toContain('-d');
    expect($cmd)->not->toContain('-D');
});

/*
 * Mutual exclusivity — only one of -d / -D may be active; last call wins.
 */
it('midpointTo() clears a previously set differentialTo() (last call wins)', function () {
    $builder = new PositionsBuilder;
    $builder->selectBodies(\DivineaLabs\Swisseph\Enums\PlanetBodySelection::MERCURY)
        ->differentialTo(PlanetBody::SUN)
        ->midpointTo(PlanetBody::CHIRON);

    $cmd = $builder->build()->toCliString();

    expect($cmd)->toContain('-D15');
    expect($cmd)->not->toContain('-d0');
});

it('differentialTo() clears a previously set midpointTo() (last call wins)', function () {
    $builder = new PositionsBuilder;
    $builder->selectBodies(\DivineaLabs\Swisseph\Enums\PlanetBodySelection::MERCURY)
        ->midpointTo(PlanetBody::CHIRON)
        ->differentialTo(PlanetBody::SUN);

    $cmd = $builder->build()->toCliString();

    expect($cmd)->toContain('-d0');
    expect($cmd)->not->toContain('-D15');
});
```

- [ ] **Step 2: Run the builder tests to verify they fail**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsRelativeTest.php`
Expected: FAIL — `differentialTo()` / `midpointTo()` are undefined methods on `PositionsBuilder`.

- [ ] **Step 3: Add the relative-mode state + fluent methods to `PositionsBuilder`**

Add two properties alongside the other builder state (near `$siderealOption` / `$houseSystem`):

```php
    /**
     * Reference body for relative output (differential -d / midpoint -D), or null when off.
     */
    private ?PlanetBody $relativeReference = null;

    /**
     * Active relative mode: 'd' (differential) or 'D' (midpoint), or null when off.
     */
    private ?string $relativeMode = null;
```

Add the two fluent methods (e.g. directly after `setObserverPosition()`):

```php
    /**
     * Output the longitude/latitude DIFFERENCE between $reference and each selected body.
     * swetest name column becomes "A-B" (e.g. "Mer-Sun"). Mutually exclusive with midpointTo().
     *
     * @return $this
     */
    public function differentialTo(PlanetBody $reference): self
    {
        $this->relativeReference = $reference;
        $this->relativeMode = 'd';

        return $this;
    }

    /**
     * Output the MIDPOINT between $reference and each selected body.
     * swetest name column becomes "A/B" (e.g. "Sat/Chi"). Mutually exclusive with differentialTo().
     *
     * @return $this
     */
    public function midpointTo(PlanetBody $reference): self
    {
        $this->relativeReference = $reference;
        $this->relativeMode = 'D';

        return $this;
    }
```

(Both setters overwrite `$relativeReference` and `$relativeMode`, so only the last call survives — this is the mutual-exclusivity guarantee. Because there is a single shared `$relativeMode` slot, it is structurally impossible for both `-d` and `-D` to be emitted.)

- [ ] **Step 4: Emit the flag in `build()`**

In `build()`, after the sidereal block and before (or after) the observer block, append the relative argument when set. Insert this immediately after the `if ($this->siderealOption) { ... }` block:

```php
        if ($this->relativeReference !== null && $this->relativeMode !== null) {
            $args[] = $this->relativeMode.$this->relativeReference->value;
        }
```

Note: `$this->relativeReference->value` is the swetest body number (e.g. `PlanetBody::SUN->value === 0`, `PlanetBody::CHIRON->value === 15`), so this yields `d0` / `D15`, rendered by `SwissephCommand::toCliString()` as `-d0` / `-D15`.

- [ ] **Step 5: Run the builder tests to verify they pass**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsRelativeTest.php`
Expected: PASS (5 builder tests). The parser tests added in Task 3 are not present yet.

- [ ] **Step 6: Commit**

```bash
git add src/Support/Positions/PositionsBuilder.php tests/Features/Support/Positions/PositionsRelativeTest.php
git commit -m "feat(positions): differentialTo()/midpointTo() relative-ephemeris modifiers"
```

---

### Task 2: Parser + DTO — composite-label body name with null index

**Files:**
- Modify: `src/Data/PlanetBodyData.php`
- Modify: `src/Support/Positions/PositionsParser.php`

- [ ] **Step 1: Make `PlanetBodyData::$index` nullable**

Edit `src/Data/PlanetBodyData.php`:

```php
<?php

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class PlanetBodyData extends Data
{
    public function __construct(
        public ?int $index,
        public string $name,
    ) {}
}
```

This is backward-compatible: existing rows still pass an `int`; composite-label rows pass `null`.

- [ ] **Step 2: Confirm the failing path (no separate test run needed)**

The parser change is exercised by the Task 3 parser tests. Before this step, `PositionsParser::parsePlanetRow()` calls `PlanetBody::from($parts[0])`, which throws `\ValueError` on a composite label like `Mer-Sun` (not a valid enum value). The Task 3 fixtures will therefore fail until Step 3 lands. (If you want an isolated red signal now, run the Task 3 parser tests; they will error on the `ValueError`.)

- [ ] **Step 3: Add the gated composite-label branch to `parsePlanetRow()`**

In `src/Support/Positions/PositionsParser.php`, replace the body-name resolution + the returned `PlanetBodyData` construction so that an unrecognized name column (composite `A-B` / `A/B`) is stored verbatim with `index = null`.

Replace the head of `parsePlanetRow()`:

```php
    private function parsePlanetRow(array $parts, array $sequence): array
    {
        $planetBody = PlanetBody::from($parts[0]);
        $properties = [];
```

with:

```php
    private function parsePlanetRow(array $parts, array $sequence): array
    {
        // A relative-ephemeris row (-d / -D) carries a composite label in the
        // name column ("Mer-Sun" / "Sat/Chi") that is NOT a PlanetBody value.
        // In that case store the raw label as the name with a null index.
        $rawName = trim($parts[0]);
        $planetBody = PlanetBody::tryFrom($rawName);
        $properties = [];
```

and replace the returned `PlanetBodyData::from([...])` at the end of the method:

```php
        return [
            'planet_body' => PlanetBodyData::from([
                'name' => $planetBody->getName(),
                'index' => $planetBody->value,
            ]),
            'properties' => $properties,
        ];
```

with:

```php
        return [
            'planet_body' => PlanetBodyData::from([
                'name' => $planetBody?->getName() ?? $rawName,
                'index' => $planetBody?->value,
            ]),
            'properties' => $properties,
        ];
```

> Note on column offset: the reference SP8 fixtures use `-fPLl` (name + longitude-dms + longitude-decimal), which carries NO numeric planet-index column. The existing property loop starts at `$seq = 2` / `$col = 2`, i.e. it skips a `[index, name]` pair. To keep the parser robust for composite rows that begin at the name column, the parser test in Task 3 constructs its `PositionsBuilder` so that `getProperties()` returns a sequence whose first two entries are consumed as the `[name, <first real property>]` framing the existing loop already expects — see Task 3 Step 1 for the exact builder setup and the resulting `$parts` framing. No change to the loop indices is required; only the name resolution and DTO construction change here.

- [ ] **Step 4: Run the parser tests (added in Task 3) — deferred**

The verification for this task is the Task 3 parser tests. Proceed to Task 3, then run the combined file.

- [ ] **Step 5: Commit**

```bash
git add src/Data/PlanetBodyData.php src/Support/Positions/PositionsParser.php
git commit -m "feat(positions): parse composite -d/-D labels as body name with null index"
```

---

### Task 3: Parser tests against the real captured fixtures + integration

**Files:**
- Modify: `tests/Features/Support/Positions/PositionsRelativeTest.php` (append parser + integration tests)

The exact captured swetest output (from `docs/superpowers/specs/swetest-output-reference.md`, section SP8):

```
Mer-SunPPP -11°39'46.5812PPP-11.6629392      # differential  -p2 -d0 -fPLl  (Mercury minus Sun)
Sat/ChiPPP   9°23'53.0285PPP  9.3980635      # midpoint       -p6 -DD -fPLl  (Saturn/Chiron)
```

Both rows follow `-fPLl`: column 0 = composite name, column 1 = longitude (ddd°mm'ss), column 2 = longitude decimal. The parser frames a planet row as `[col0, col1, <props...>]` and begins reading properties at sequence index 2, so the builder for this test is configured with a property sequence whose first two slots are `PLANET_NAME` (the framing name slot) and `LONGITUDE_DEGREE`, followed by `LONGITUDE_DECIMAL` as the first read property. This makes the `-fPLl` capture map cleanly: `name = parts[0]`, `longitude_degree = parts[1]`, `longitude_decimal = parts[2]`.

- [ ] **Step 1: Append the parser tests**

Append to `tests/Features/Support/Positions/PositionsRelativeTest.php`:

```php
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Support\Positions\PositionsParser;

/**
 * Build a PositionsBuilder whose property sequence matches the captured
 * `-fPLl` relative output: [PLANET_NAME, LONGITUDE_DEGREE, LONGITUDE_DECIMAL].
 * The parser frames the row as [name, <first prop>, <rest...>] and starts
 * reading properties at sequence index 2, so LONGITUDE_DECIMAL is the value
 * mapped from the decimal column.
 */
function relativeBuilder(): PositionsBuilder
{
    $builder = new PositionsBuilder;
    // Reset the default property sequence to the -fPLl shape used by the SP8 capture.
    $ref = new ReflectionObject($builder);

    $defaults = $ref->getProperty('defaultProperties');
    $defaults->setAccessible(true);
    $defaults->setValue($builder, [
        AstroProperties::PLANET_NAME,
        AstroProperties::LONGITUDE_DEGREE,
        AstroProperties::LONGITUDE_DECIMAL,
    ]);

    $custom = $ref->getProperty('customProperties');
    $custom->setAccessible(true);
    $custom->setValue($builder, []);

    return $builder;
}

it('parses a differential (-d) row: composite name + null index + longitude values', function () {
    $builder = relativeBuilder();
    $parser = new PositionsParser;

    // Exact captured line (docs/.../swetest-output-reference.md §SP8, differential).
    $lines = ["Mer-SunPPP -11°39'46.5812PPP-11.6629392"];

    $frame = $parser->parse($lines, $builder);

    expect($frame->planet_bodies)->toHaveCount(1);

    $row = $frame->planet_bodies[0];
    expect($row['planet_body']->name)->toBe('Mer-Sun');
    expect($row['planet_body']->index)->toBeNull();

    $byKey = collect($row['properties'])->keyBy('property');
    expect($byKey['longitude_degree']->value)->toBe("-11°39'46.5812");
    expect($byKey['longitude_decimal']->value)->toBe('-11.6629392');
});

it('parses a midpoint (-D) row: composite name + null index + longitude values', function () {
    $builder = relativeBuilder();
    $parser = new PositionsParser;

    // Exact captured line (docs/.../swetest-output-reference.md §SP8, midpoint).
    $lines = ["Sat/ChiPPP   9°23'53.0285PPP  9.3980635"];

    $frame = $parser->parse($lines, $builder);

    expect($frame->planet_bodies)->toHaveCount(1);

    $row = $frame->planet_bodies[0];
    expect($row['planet_body']->name)->toBe('Sat/Chi');
    expect($row['planet_body']->index)->toBeNull();

    $byKey = collect($row['properties'])->keyBy('property');
    expect($byKey['longitude_degree']->value)->toBe("9°23'53.0285");
    expect($byKey['longitude_decimal']->value)->toBe('9.3980635');
});
```

> The parser does `array_map('trim', ...)` on single-column values, so the leading spaces in `   9°23'53.0285` and `  9.3980635` are trimmed to `9°23'53.0285` and `9.3980635`. The differential decimal `-11.6629392` has no leading space and trims to itself.

- [ ] **Step 2: Append the self-skipping integration test**

```php
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;
use DivineaLabs\Swisseph\Facades\Swisseph;

it('computes a real differential row end-to-end (skips without the swetest binary)', function () {
    $exe = (string) config('swisseph.executable', '');
    if ($exe === '' || ! is_executable($exe)) {
        test()->markTestSkipped('swetest binary not available.');
    }

    $frame = Swisseph::positions()
        ->setDateTime('2026-01-01 12:00:00', 'UTC')
        ->selectBodies(PlanetBodySelection::MERCURY)
        ->withProperties(AstroProperties::LONGITUDE_DEGREE, AstroProperties::LONGITUDE_DECIMAL)
        ->differentialTo(PlanetBody::SUN)
        ->get();

    expect($frame->planet_bodies)->not->toBeEmpty();

    $row = $frame->planet_bodies[0];
    // Differential rows carry a composite "A-B" label and no numeric index.
    expect($row['planet_body']->name)->toContain('-');
    expect($row['planet_body']->index)->toBeNull();
});
```

> If SP0 is NOT applied, replace the `Swisseph::positions()->…->get()` chain with the pre-SP0 equivalent (`Swisseph::setDateTime(...)->selectBodies(...)->differentialTo(...)` then the existing terminal `get()` on the facade). The assertions are unchanged.

- [ ] **Step 3: Run the full SP8 test file**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsRelativeTest.php`
Expected: PASS — 5 builder tests + 2 parser tests pass; the integration test is SKIPPED when no binary is configured, or PASSES when `swisseph.executable` points at a real `swetest`.

- [ ] **Step 4: Run the whole positions suite (regression)**

Run: `vendor/bin/pest tests/Features/Support/Positions/`
Expected: PASS — the existing `PositionsBuilderTest` / `PositionsParserTest` still pass; the nullable-index change to `PlanetBodyData` does not alter their integer-index assertions.

- [ ] **Step 5: Run the full suite + static analysis**

Run: `vendor/bin/pest`
Expected: PASS (all suites green; SP8 integration test skipped without binary).

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors. The nullable `$index` and `?->` access in the parser are type-safe; fix any stale `int` type assumptions the change surfaces (e.g. consumers that assumed a non-null index).

- [ ] **Step 6: Commit**

```bash
git add tests/Features/Support/Positions/PositionsRelativeTest.php
git commit -m "test(positions): cover -d/-D builder flags, mutual-exclusivity, composite-label parsing"
```

---

## Self-Review notes

- **Spec coverage (SP8):** `differentialTo()` → `-d<ref>` (Task 1), `midpointTo()` → `-D<ref>` (Task 1), mutual exclusivity / last-call-wins via a single shared `$relativeMode` slot (Task 1 Steps 3 + builder tests in Step 1), composite-label parsing with null index (Task 2), nullable `PlanetBodyData::$index` (Task 2 Step 1), no new DTO (results via the normal `AstroTimeFrame`). ✅
- **Exact fixtures:** the two captured lines `Mer-SunPPP -11°39'46.5812PPP-11.6629392` and `Sat/ChiPPP   9°23'53.0285PPP  9.3980635` are copied verbatim from `swetest-output-reference.md §SP8` and asserted field-by-field (composite name, null index, longitude degree + decimal). ✅
- **Flag-value correctness:** `-d0` (Sun = 0) and `-D15` (Chiron = 15) follow `PlanetBody->value`; the midpoint test explicitly guards against `-D15` being misread as `-d15`. ✅
- **Gating / no conflict:** the parser branch is additive and only fires when `PlanetBody::tryFrom()` is null, so it coexists with SP3's non-numeric name handling regardless of ordering; the SP0 note explains how to retarget the same edits to the pre-SP0 classes. ✅
- **Regression safety:** `PlanetBodyData::$index` widening `int → ?int` is source-compatible for existing callers; the existing positions builder/parser tests are unaffected and re-run in Task 3 Step 4. ✅
- **No commit of unrelated work; commits scoped per task. Integration test self-skips without the binary (package convention).** ✅
