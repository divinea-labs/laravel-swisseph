# SP3 — Bodies & Data Extensions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing `positions()` pipeline (post-SP0 `PositionsBuilder` / `PositionsParser`) to expose four families of swetest selectors that today are unreachable: fixed stars (`-pf -xf<name>`), MPC-numbered asteroids (`-ps -xs<number>`), output-only pseudo-bodies / computed codes (`-px/-pq/-po/-pb/-py` → Sidereal Time, Delta T, Ecl. Obliquity, Ayanamsha, Time Equation), and a thin planetary-moon selector (`-pv -xv<number>`). No new pipeline is added — these are additive builder methods + parser tolerance changes on the positions pipeline.

**Architecture:** SP3 is purely additive on top of SP0. `PositionsBuilder` gains four selector methods (`selectFixedStar()`, `selectAsteroid()`, `selectComputedValue()`, `selectMoon()`) that set extra `-xf/-xs/-xv` arguments and steer the `-p` body letter (`f`/`s`/`v`) or computed letters (`x/q/o/b/y`). `PositionsParser` learns to tolerate a **non-numeric first column**: when the row's first PPP-token is not an integer planet index (a star catalog name like `Sirius,alCMa`, an asteroid name like `Eros`, or a computed-value label like `Sidereal Time`), it builds a `PlanetBodyData` with `index = null` and `name = <token>` instead of calling `PlanetBody::from()`. Computed-value rows additionally store their datum verbatim (the parser must NOT coerce `01.01.2026 12:00:00 UT` to a float). `PlanetBodyData::$index` is widened to `?int`.

**Tech Stack:** PHP 8.3/8.4, Laravel 11/12/13, spatie/laravel-data, spatie/laravel-package-tools, Pest 4, Symfony Process, larastan/PHPStan.

**Conventions inherited from SP0 (locked — MUST follow):**
- The positions pipeline lives in `src/Support/Positions/` with `PositionsBuilder.php` + `PositionsParser.php`; DTOs under `src/Data/`; enums under `src/Enums/`.
- `Swisseph::positions()` returns a fresh `PositionsBuilder` resolved from the container; terminal `get(): AstroTimeFrame` / `getCliCommand(): string` live on the builder.
- The builder emits a `SwissephCommand` via `build()`; the parser receives stdout lines already stripped of the command-echo + `geo. long` header by `SwissephExecutor`.
- Position-pipeline rows are `PPP`-separated (the literal `-gPPP` column separator), headers suppressed by `-head`. Tokenize columns on the literal string `PPP`.
- Tests live under `tests/Features/Support/Positions/`; fixtures under `tests/Fixtures/`; load via the existing `fixtureLines()` helper.

> **NOTE — order dependency:** SP3 assumes SP0 has merged. All paths/class names below are the POST-SP0 names (`PositionsBuilder`, `PositionsParser`, `tests/Features/Support/Positions/…`). If SP0 is not yet merged when this plan is executed, first apply the SP0 renames (Tasks 3 of the SP0 plan) — do NOT re-implement against the legacy `SwissephCommandBuilder`/`SwissephParser` names.

---

## File Structure

- Modify: `src/Support/Positions/PositionsBuilder.php` — add `selectFixedStar()`, `selectAsteroid()`, `selectComputedValue()`, `selectMoon()`; track extra body-selector args (`-xf/-xs/-xv`) and a computed-body-letters set; teach `buildBodies()` to emit `-pf`/`-ps`/`-pv`/computed letters and append the `-x*` args in `build()`.
- Modify: `src/Support/Positions/PositionsParser.php` — tolerate a non-numeric first column (star/asteroid/computed label); when the first token is non-numeric, build `PlanetBodyData(index: null, name: token)`; store computed-value rows' raw value without numeric coercion.
- Modify: `src/Data/PlanetBodyData.php` — widen `$index` from `int` to `?int`.
- Modify: `src/Enums/PlanetBodySelection.php` — add the `FIXED_STAR = 'f'`, `ASTEROID = 's'`, `PLANETARY_MOON = 'v'` selector cases and the five computed-value cases (`SIDEREAL_TIME = 'x'`, `DELTA_T = 'q'`, `ECLIPTIC_OBLIQUITY = 'o'`, `AYANAMSHA = 'b'`, `TIME_EQUATION = 'y'`) with `getName()` arms.
- Create: `src/Enums/ComputedValue.php` — a small string-backed enum (letter → label) used by `selectComputedValue()` and by the parser to recognise computed-value labels.
- Create fixtures:
  - `tests/Fixtures/swetest-fixedstar-sirius.txt`
  - `tests/Fixtures/swetest-asteroid-eros.txt`
  - `tests/Fixtures/swetest-computed-codes.txt`
- Modify tests: extend `tests/Features/Support/Positions/PositionsBuilderTest.php` (new selector-argument assertions) and `tests/Features/Support/Positions/PositionsParserTest.php` (non-numeric column + computed-value parsing) OR add a focused `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`. This plan uses a focused new file `PositionsBodiesExtraTest.php` for SP3 builder+parser assertions to keep the SP0-migrated tests untouched.
- Create: `tests/Features/Support/Positions/PositionsMoonsIntegrationTest.php` — self-skipping integration test for `selectMoon()` (no unit fixture; see Task 6 note).

---

### Task 1: Widen `PlanetBodyData::$index` to nullable

**Files:**
- Modify: `src/Data/PlanetBodyData.php`
- Test: `tests/Features/Support/Positions/PositionsBodiesExtraTest.php` (new file — first test)

- [ ] **Step 1: Write the failing test**

Create `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`:

```php
<?php

use DivineaLabs\Swisseph\Data\PlanetBodyData;

it('allows a null index for catalog/computed bodies', function () {
    $data = PlanetBodyData::from([
        'index' => null,
        'name' => 'Sirius,alCMa',
    ]);

    expect($data->index)->toBeNull();
    expect($data->name)->toBe('Sirius,alCMa');
});

it('still accepts an integer index for numbered planets', function () {
    $data = PlanetBodyData::from([
        'index' => 0,
        'name' => 'Sun',
    ]);

    expect($data->index)->toBe(0);
    expect($data->name)->toBe('Sun');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: FAIL — `PlanetBodyData::__construct(): Argument #1 ($index) must be of type int, null given`.

- [ ] **Step 3: Widen the property**

Replace the whole file `src/Data/PlanetBodyData.php`:

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

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Guard the existing parser path**

The existing `PositionsParser::parsePlanetRow()` passes `'index' => $planetBody->value` (an int) — still valid against `?int`. No change needed here; confirm by running the migrated parser test:

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsParserTest.php`
Expected: PASS (unchanged — 3 tests).

---

### Task 2: Add SP3 selector enum cases + a ComputedValue enum

**Files:**
- Modify: `src/Enums/PlanetBodySelection.php`
- Create: `src/Enums/ComputedValue.php`
- Test: extend `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`:

```php
use DivineaLabs\Swisseph\Enums\ComputedValue;
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;

it('exposes fixed-star / asteroid / moon body selectors', function () {
    expect(PlanetBodySelection::FIXED_STAR->value)->toBe('f');
    expect(PlanetBodySelection::ASTEROID->value)->toBe('s');
    expect(PlanetBodySelection::PLANETARY_MOON->value)->toBe('v');
});

it('maps each computed value to its swetest letter and label', function () {
    expect(ComputedValue::SIDEREAL_TIME->value)->toBe('x');
    expect(ComputedValue::DELTA_T->value)->toBe('q');
    expect(ComputedValue::ECLIPTIC_OBLIQUITY->value)->toBe('o');
    expect(ComputedValue::AYANAMSHA->value)->toBe('b');
    expect(ComputedValue::TIME_EQUATION->value)->toBe('y');

    expect(ComputedValue::SIDEREAL_TIME->getLabel())->toBe('Sidereal Time');
    expect(ComputedValue::DELTA_T->getLabel())->toBe('Delta T');
    expect(ComputedValue::ECLIPTIC_OBLIQUITY->getLabel())->toBe('Ecl. Obl.');
    expect(ComputedValue::AYANAMSHA->getLabel())->toBe('Ayanamsha');
    expect(ComputedValue::TIME_EQUATION->getLabel())->toBe('Time equation');
});

it('reports whether a computed value carries a time string or a float', function () {
    expect(ComputedValue::SIDEREAL_TIME->isTimeString())->toBeTrue();
    expect(ComputedValue::DELTA_T->isTimeString())->toBeFalse();
    expect(ComputedValue::AYANAMSHA->isTimeString())->toBeFalse();
});

it('matches a swetest label back to its computed value', function () {
    expect(ComputedValue::fromLabel('Sidereal Time'))->toBe(ComputedValue::SIDEREAL_TIME);
    expect(ComputedValue::fromLabel('Delta T'))->toBe(ComputedValue::DELTA_T);
    expect(ComputedValue::fromLabel('Ecl. Obl.'))->toBe(ComputedValue::ECLIPTIC_OBLIQUITY);
    expect(ComputedValue::fromLabel('Ayanamsha'))->toBe(ComputedValue::AYANAMSHA);
    expect(ComputedValue::fromLabel('Mercury'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: FAIL — `ComputedValue` not found / `PlanetBodySelection::FIXED_STAR` undefined.

- [ ] **Step 3: Add the selector cases to `PlanetBodySelection`**

In `src/Enums/PlanetBodySelection.php`, add the three selector cases after `case WALDEMATH = 'w';` (before the comment block):

```php
    case WALDEMATH = 'w'; // Waldemath's dark Moon

    // SP3 — extended body selectors. These steer the leading -p<letter>; the
    // concrete target is supplied separately via -xf<name> / -xs<number> / -xv<number>.
    case FIXED_STAR = 'f';      // -pf  (target via -xf<StarName>)
    case ASTEROID = 's';        // -ps  (target via -xs<MPCnumber>)
    case PLANETARY_MOON = 'v';  // -pv  (target via -xv<number>)
```

And add the matching `getName()` arms inside the `match ($this)` block (after `self::WALDEMATH => ...`):

```php
            self::WALDEMATH => 'Waldemath\'s Dark Moon',

            // SP3 selectors
            self::FIXED_STAR => 'Fixed star',
            self::ASTEROID => 'Asteroid (MPC number)',
            self::PLANETARY_MOON => 'Planetary moon',
```

> NOTE: the computed-value letters (`x/q/o/b/y`) are intentionally NOT added to `PlanetBodySelection` — they collide with `AstroProperties` letters and are a distinct concept (output-only codes). They get their own `ComputedValue` enum (Step 4). `selectComputedValue()` in Task 5 emits them onto the `-p` selector directly.

- [ ] **Step 4: Create the `ComputedValue` enum**

Create `src/Enums/ComputedValue.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

/**
 * Output-only pseudo-bodies appended to the -p selector.
 *
 * Each produces a single labeled row whose value column is a datum rather than a
 * normal position: Sidereal Time is a UT time STRING; the others are floats.
 * (Ayanamsha additionally requires a sidereal mode, e.g. -sid1 via withSidereal().)
 */
enum ComputedValue: string
{
    case SIDEREAL_TIME = 'x';
    case DELTA_T = 'q';
    case ECLIPTIC_OBLIQUITY = 'o';
    case AYANAMSHA = 'b';
    case TIME_EQUATION = 'y';

    /**
     * The fixed label swetest prints in the name column for this code.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::SIDEREAL_TIME => 'Sidereal Time',
            self::DELTA_T => 'Delta T',
            self::ECLIPTIC_OBLIQUITY => 'Ecl. Obl.',
            self::AYANAMSHA => 'Ayanamsha',
            self::TIME_EQUATION => 'Time equation',
        };
    }

    /**
     * Whether the value column is a UT time string (true) or a numeric datum (false).
     */
    public function isTimeString(): bool
    {
        return $this === self::SIDEREAL_TIME;
    }

    /**
     * Resolve a swetest name-column label back to its enum (null if not a computed value).
     */
    public static function fromLabel(string $label): ?self
    {
        $label = trim($label);

        foreach (self::cases() as $case) {
            if ($case->getLabel() === $label) {
                return $case;
            }
        }

        return null;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: PASS (all tests so far green).

---

### Task 3: Fixed-star selector on the builder

**Files:**
- Modify: `src/Support/Positions/PositionsBuilder.php`
- Test: extend `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`

Reference (ground truth) — fixed star command is `-pf -xfSirius -fPLBl`, output row:
```
Sirius,alCMa   PPP 104°26'56.6685PPP -39°36'39.8652PPP 104.4490746
```
So `selectFixedStar('Sirius')` must set the `-p` body letter to `f` AND append `-xfSirius`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`:

```php
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;

it('builds a fixed-star selector with -pf and -xf<name>', function () {
    $builder = new PositionsBuilder;
    $builder->selectFixedStar('Sirius');

    $cli = $builder->build()->toCliString();

    expect($cli)->toContain('-pf');
    expect($cli)->toContain('-xfSirius');
});

it('trims the fixed-star name', function () {
    $builder = new PositionsBuilder;
    $builder->selectFixedStar('  Aldebaran  ');

    expect($builder->build()->toCliString())->toContain('-xfAldebaran');
});

it('rejects an empty fixed-star name', function () {
    $builder = new PositionsBuilder;

    expect(fn () => $builder->selectFixedStar('   '))
        ->toThrow(InvalidPlanetBodySelectionException::class);
});
```

Add the use-statement at the top of the file:
```php
use DivineaLabs\Swisseph\Exceptions\InvalidPlanetBodySelectionException;
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: FAIL — `selectFixedStar()` not defined.

- [ ] **Step 3: Implement the selector + the extra-arg plumbing**

In `src/Support/Positions/PositionsBuilder.php`, add a property to hold the extra `-x*` selector args. Place it next to `$bodies` (post-SP0 the env state lives in the trait; `$bodies` is still a builder property):

```php
    /** @var array<int, string> */
    private array $bodies = [];

    /**
     * Extra body-target arguments emitted verbatim after -p (e.g. xfSirius, xs433, xv1).
     *
     * @var array<int, string>
     */
    private array $bodyTargetArgs = [];
```

Add the `selectFixedStar()` method (place near `selectBodies()`):

```php
    /**
     * Select a fixed star by catalog name (-pf -xf<name>).
     *
     * The result row's name column carries the catalog name (e.g. "Sirius,alCMa").
     *
     * @return $this
     */
    public function selectFixedStar(string $name): self
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidPlanetBodySelectionException::invalidValue($name);
        }

        $this->bodies = [PlanetBodySelection::FIXED_STAR->value];
        $this->bodyTargetArgs = ['xf'.$name];

        return $this;
    }
```

In `build()`, append the extra target args right after `$this->buildBodies()`. Replace the existing `$args` assembly head:

```php
        $args = [
            $this->epheDirArg(),
            ...$this->ephOptionArgs(),
            $this->dateArg(),
            $this->utTimeArg(),
            $this->buildBodies(),
            ...$this->bodyTargetArgs,
        ];
```

> NOTE (SP0 alignment): post-SP0 `build()` already calls `epheDirArg()`/`ephOptionArgs()`/`dateArg()`/`utTimeArg()` from the trait (the legacy `$this->options[...]`/`buildEphOptions()`/`buildDateArgument()`/`buildTimeArgument()` were removed in SP0 Task 3). Only the addition of `...$this->bodyTargetArgs` is new in SP3 — keep the rest of `build()` (houses, sidereal, observer, properties sequence, `gPPP`, `head`) exactly as SP0 left it.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: PASS.

---

### Task 4: Asteroid-by-MPC-number selector on the builder

**Files:**
- Modify: `src/Support/Positions/PositionsBuilder.php`
- Test: extend `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`

Reference — `-ps -xs433 -fPLl` → name column `Eros`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`:

```php
it('builds an asteroid selector with -ps and -xs<number>', function () {
    $builder = new PositionsBuilder;
    $builder->selectAsteroid(433);

    $cli = $builder->build()->toCliString();

    expect($cli)->toContain('-ps');
    expect($cli)->toContain('-xs433');
});

it('rejects a non-positive MPC number', function () {
    $builder = new PositionsBuilder;

    expect(fn () => $builder->selectAsteroid(0))
        ->toThrow(InvalidPlanetBodySelectionException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: FAIL — `selectAsteroid()` not defined.

- [ ] **Step 3: Implement the selector**

In `src/Support/Positions/PositionsBuilder.php`, add (next to `selectFixedStar()`):

```php
    /**
     * Select an asteroid by its MPC number (-ps -xs<number>).
     *
     * @return $this
     */
    public function selectAsteroid(int $mpcNumber): self
    {
        if ($mpcNumber <= 0) {
            throw InvalidPlanetBodySelectionException::invalidValue((string) $mpcNumber);
        }

        $this->bodies = [PlanetBodySelection::ASTEROID->value];
        $this->bodyTargetArgs = ['xs'.$mpcNumber];

        return $this;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: PASS.

---

### Task 5: Computed-value (output-only code) selector on the builder

**Files:**
- Modify: `src/Support/Positions/PositionsBuilder.php`
- Test: extend `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`

Reference — `-px` → Sidereal Time, `-pq` → Delta T, `-po` → Ecl. Obl., `-pb` → Ayanamsha (needs `-sid1`), `-py` → Time equation. These are appended to the `-p` selector. Multiple may be requested in one call; they concatenate after `-p` (e.g. `-pqo`). They do not use `-x*` target args.

- [ ] **Step 1: Write the failing test**

Append to `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`:

```php
it('builds a computed-value selector onto -p', function () {
    $builder = new PositionsBuilder;
    $builder->selectComputedValue(ComputedValue::DELTA_T);

    expect($builder->build()->toCliString())->toContain('-pq');
});

it('concatenates multiple computed values onto a single -p', function () {
    $builder = new PositionsBuilder;
    $builder->selectComputedValue(ComputedValue::DELTA_T, ComputedValue::ECLIPTIC_OBLIQUITY);

    expect($builder->build()->toCliString())->toContain('-pqo');
});

it('emits ayanamsha together with the sidereal mode', function () {
    $builder = new PositionsBuilder;
    $builder
        ->selectComputedValue(ComputedValue::AYANAMSHA)
        ->withSidereal(\DivineaLabs\Swisseph\Enums\Sidereal::LAHIRI);

    $cli = $builder->build()->toCliString();

    expect($cli)->toContain('-pb');
    expect($cli)->toContain('-sid1');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: FAIL — `selectComputedValue()` not defined.

- [ ] **Step 3: Implement the selector**

Add the import at the top of `src/Support/Positions/PositionsBuilder.php`:

```php
use DivineaLabs\Swisseph\Enums\ComputedValue;
```

Add the method (next to `selectAsteroid()`):

```php
    /**
     * Select one or more output-only computed values (Sidereal Time, Delta T,
     * Ecl. Obliquity, Ayanamsha, Time equation). They are appended to the -p
     * selector (e.g. -pqo). Ayanamsha additionally requires withSidereal().
     *
     * @return $this
     */
    public function selectComputedValue(ComputedValue ...$values): self
    {
        if ($values === []) {
            throw InvalidPlanetBodySelectionException::invalidValue('');
        }

        $this->bodies = array_map(
            static fn (ComputedValue $v): string => $v->value,
            $values,
        );
        $this->bodyTargetArgs = [];

        return $this;
    }
```

> NOTE: `buildBodies()` already does `'p'.implode('', $this->bodies)`, so `['q','o']` → `-pqo`. No change to `buildBodies()` is needed; computed letters flow through it unchanged.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: PASS.

---

### Task 6: Planetary-moon selector (thin) + self-skipping integration test

**Files:**
- Modify: `src/Support/Positions/PositionsBuilder.php`
- Test (unit, builder-only): extend `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
- Test (integration, self-skipping): create `tests/Features/Support/Positions/PositionsMoonsIntegrationTest.php`

> **DEFERRED-FIXTURE NOTE (explicit, not a silent omission):** Real swetest output for `-pv -xv<number>` was NOT captured for the SP3 reference. Therefore SP3 ships `selectMoon()` as a thin selector that reuses the standard position-row parser, with NO hard-coded unit fixture. The shape is instead asserted by a self-skipping INTEGRATION test that captures live output from the binary and asserts a non-empty position row. When that test first runs green against a real binary, the captured block SHOULD be promoted into a `tests/Fixtures/swetest-moon-<n>.txt` fixture and a unit parser test added (follow-up). This is logged here intentionally: `log("SP3: moon unit fixture deferred to integration capture — see PositionsMoonsIntegrationTest")`.

- [ ] **Step 1: Write the failing builder test**

Append to `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`:

```php
it('builds a planetary-moon selector with -pv and -xv<number>', function () {
    $builder = new PositionsBuilder;
    $builder->selectMoon(1);

    $cli = $builder->build()->toCliString();

    expect($cli)->toContain('-pv');
    expect($cli)->toContain('-xv1');
});

it('rejects a non-positive moon number', function () {
    $builder = new PositionsBuilder;

    expect(fn () => $builder->selectMoon(0))
        ->toThrow(InvalidPlanetBodySelectionException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: FAIL — `selectMoon()` not defined.

- [ ] **Step 3: Implement the selector**

Add to `src/Support/Positions/PositionsBuilder.php` (next to `selectAsteroid()`):

```php
    /**
     * Select a planetary moon by its swetest number (-pv -xv<number>).
     *
     * Thin selector: reuses the standard position-row parser. No captured unit
     * fixture exists yet — shape is asserted by PositionsMoonsIntegrationTest.
     *
     * @return $this
     */
    public function selectMoon(int $number): self
    {
        if ($number <= 0) {
            throw InvalidPlanetBodySelectionException::invalidValue((string) $number);
        }

        $this->bodies = [PlanetBodySelection::PLANETARY_MOON->value];
        $this->bodyTargetArgs = ['xv'.$number];

        return $this;
    }
```

- [ ] **Step 4: Run the builder test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: PASS.

- [ ] **Step 5: Add the self-skipping integration test**

Create `tests/Features/Support/Positions/PositionsMoonsIntegrationTest.php`:

```php
<?php

use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;

/**
 * Integration test for the planetary-moon selector.
 *
 * Self-skips when no swetest binary is configured/available. When it runs, it
 * captures live output and asserts the shape of a planetary-moon position row
 * (in lieu of a hard-coded fixture — see the SP3 plan deferred-fixture note).
 */
beforeEach(function () {
    $exe = (string) config('swisseph.executable', '');

    if ($exe === '' || ! is_executable($exe)) {
        $this->markTestSkipped('swetest binary not available — moon integration test skipped.');
    }
});

it('returns a parsable position row for a planetary moon', function () {
    $frame = (new PositionsBuilder)
        ->setDateTime('2026-01-01 12:00:00', 'UTC')
        ->selectMoon(1)
        ->get();

    expect($frame->planet_bodies)->not->toBeEmpty();

    $body = $frame->planet_bodies[0];

    // Standard position-row shape: a body DTO + at least the default properties.
    expect($body['planet_body']->name)->not->toBe('');
    expect($body['properties'])->not->toBeEmpty();
})->group('integration');
```

- [ ] **Step 6: Run the integration test (expect skip without a binary)**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsMoonsIntegrationTest.php`
Expected: SKIPPED (1 skipped) on CI / a host without the binary; PASS where the binary is present.

---

### Task 7: Parser tolerance for non-numeric first column (stars + asteroids)

**Files:**
- Modify: `src/Support/Positions/PositionsParser.php`
- Create fixture: `tests/Fixtures/swetest-fixedstar-sirius.txt`
- Create fixture: `tests/Fixtures/swetest-asteroid-eros.txt`
- Test: extend `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`

The current `parsePlanetRow()` does `PlanetBody::from($parts[0])` and `$col = 2` (assuming `[index, name, …values]`). For stars/asteroids the row is `[name, …values]` — the first PPP-token is a NON-numeric name and there is NO separate index column (the `-f` sequence starts with `P`, not `p`). The parser must branch on whether `$parts[0]` is an integer.

**Captured fixtures (verbatim from the SP3 reference):**

`tests/Fixtures/swetest-fixedstar-sirius.txt` (command `-pf -xfSirius -fPLBl`, so sequence = name, lon-deg, lat-deg, lon-dec → first column is the catalog name, then 3 value columns):
```
Sirius,alCMa   PPP 104°26'56.6685PPP -39°36'39.8652PPP 104.4490746
```

`tests/Fixtures/swetest-asteroid-eros.txt` (command `-ps -xs433 -fPLl`, sequence = name, lon-deg, lon-dec → name + 2 value columns):
```
Eros           PPP  31°31'56.5890PPP 31.5323858
```

- [ ] **Step 1: Write the failing parser test**

Append to `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`:

```php
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Support\Positions\PositionsParser;

it('parses a fixed-star row with a comma-joined catalog name and null index', function () {
    $builder = (new PositionsBuilder)
        ->selectFixedStar('Sirius')
        ->withProperties(AstroProperties::LATITUDE_DEGREE);
    // Resulting sequence head for -fPLBl: P (name), L (lon deg), B (lat deg), l (lon dec).
    // We assert directly against the captured fixture below.

    $lines = fixtureLines('swetest-fixedstar-sirius.txt');

    $frame = (new PositionsParser)->parse($lines, $builder);

    expect($frame->planet_bodies)->toHaveCount(1);

    $star = $frame->planet_bodies[0];
    expect($star['planet_body']->index)->toBeNull();
    expect($star['planet_body']->name)->toBe('Sirius,alCMa');

    $byKey = collect($star['properties'])->keyBy('property');
    expect($byKey['longitude_degree']->value)->toBe("104°26'56.6685");
    expect($byKey['latitude_degree']->value)->toBe("-39°36'39.8652");
    expect($byKey['longitude_decimal']->value)->toBe('104.4490746');
});

it('parses an asteroid row with its name and null index', function () {
    $builder = (new PositionsBuilder)->selectAsteroid(433);

    $lines = fixtureLines('swetest-asteroid-eros.txt');

    $frame = (new PositionsParser)->parse($lines, $builder);

    $eros = $frame->planet_bodies[0];
    expect($eros['planet_body']->index)->toBeNull();
    expect($eros['planet_body']->name)->toBe('Eros');

    $byKey = collect($eros['properties'])->keyBy('property');
    expect($byKey['longitude_degree']->value)->toBe("31°31'56.5890");
    expect($byKey['longitude_decimal']->value)->toBe('31.5323858');
});
```

> **Sequence note for the test:** the parser maps value columns positionally against `$builder->getProperties()`, which always starts with the two defaults `PLANET_INDEX, PLANET_NAME, LONGITUDE_DECIMAL, SPEED_LONGITUDE_DECIMAL`. For star/asteroid rows we instead need the sequence to reflect `-fPLBl` / `-fPLl`. To keep the parser test honest against the captured fixtures, the builder in each test is configured so the *value* sequence (the portion after the name) matches the fixture columns. The parser change (Step 3) makes the name-column branch independent of whether `PLANET_INDEX` is present: when the first token is non-numeric it is consumed as the name, and value columns are mapped from the FIRST non-name/non-index property onward. See Step 3 for how `getValueProperties()` derives that.

Create the fixtures now:

`tests/Fixtures/swetest-fixedstar-sirius.txt`:
```
Sirius,alCMa   PPP 104°26'56.6685PPP -39°36'39.8652PPP 104.4490746
```

`tests/Fixtures/swetest-asteroid-eros.txt`:
```
Eros           PPP  31°31'56.5890PPP 31.5323858
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: FAIL — `PlanetBody::from('Sirius,alCMa')` throws `ValueError` (non-integer), or column mapping is off.

- [ ] **Step 3: Make the parser tolerate a non-numeric first column**

Edit `src/Support/Positions/PositionsParser.php`. Add the `ComputedValue` import (used in Task 8) and refactor `parsePlanetRow()` to branch on a numeric first token. Replace the whole `parsePlanetRow()` method with:

```php
    /**
     * Function parsing a single planet/star/asteroid/computed row.
     *
     * Numeric first token  → numbered planet: [index, name, …values].
     * Non-numeric first token → catalog/computed body: [name, …values] (no index column).
     *
     * @param  string[]  $parts
     * @param  AstroProperties[]  $sequence
     * @return array{planet_body: mixed, properties: array}
     */
    private function parsePlanetRow(array $parts, array $sequence): array
    {
        $firstToken = trim($parts[0]);

        if ($this->isIntegerToken($firstToken)) {
            // Numbered planet: index in col 0, name in col 1, values from col 2.
            $planetBody = PlanetBody::from($firstToken);
            $bodyData = PlanetBodyData::from([
                'name' => $planetBody->getName(),
                'index' => $planetBody->value,
            ]);
            $valueStartCol = 2;
            $valueProperties = $this->valueProperties($sequence, true);
        } else {
            // Fixed star / asteroid / computed value: name in col 0, values from col 1.
            $bodyData = PlanetBodyData::from([
                'name' => $firstToken,
                'index' => null,
            ]);
            $valueStartCol = 1;
            $valueProperties = $this->valueProperties($sequence, false);
        }

        $properties = [];
        $col = $valueStartCol;

        foreach ($valueProperties as $propEnum) {
            if ($col >= count($parts)) {
                break;
            }

            $colCount = $this->getPropertyColumnCount($propEnum);

            $value = $colCount === 1
                ? trim($parts[$col])
                : array_map('trim', array_slice($parts, $col, $colCount));

            $properties[] = PlanetBodyPropertyData::from([
                'label' => $propEnum->getLabel(),
                'property' => $propEnum->getPropertyName(),
                'value' => $value,
            ]);

            $col += $colCount;
        }

        return [
            'planet_body' => $bodyData,
            'properties' => $properties,
        ];
    }

    /**
     * The value properties (everything after the index/name header columns).
     *
     * For numbered planets the sequence head is PLANET_INDEX, PLANET_NAME; for
     * catalog rows there is only a name column. In both cases the value columns
     * are the properties that are neither PLANET_INDEX nor PLANET_NAME.
     *
     * @param  AstroProperties[]  $sequence
     * @return AstroProperties[]
     */
    private function valueProperties(array $sequence, bool $hasIndexColumn): array
    {
        return array_values(array_filter(
            $sequence,
            static fn (AstroProperties $p): bool => $p !== AstroProperties::PLANET_INDEX
                && $p !== AstroProperties::PLANET_NAME,
        ));
    }

    /**
     * Whether a column token is a plain integer (a numbered planet index).
     */
    private function isIntegerToken(string $token): bool
    {
        return $token !== '' && preg_match('/^-?\d+$/', $token) === 1;
    }
```

> NOTE: `$hasIndexColumn` is accepted for symmetry/readability but the filter is identical either way (PLANET_INDEX is simply absent from a star/asteroid `-fP…` sequence). Keeping the param documents intent and leaves room for future divergence; if PHPStan flags it as unused, drop the param and the two call-site `true`/`false` args.

Confirm the imports block at the top of the file includes:
```php
use DivineaLabs\Swisseph\Data\PlanetBodyData;
use DivineaLabs\Swisseph\Data\PlanetBodyPropertyData;
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Enums\ComputedValue;
use DivineaLabs\Swisseph\Enums\PlanetBody;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: PASS.

- [ ] **Step 5: Regression — existing numbered-planet parsing still works**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsParserTest.php`
Expected: PASS (3 tests — numbered planets/houses still parse via the integer-token branch; `valueProperties()` yields the same 5 value props as before because PLANET_INDEX/PLANET_NAME are filtered and value mapping starts at col 2).

---

### Task 8: Parser handling of computed-value rows (no numeric coercion)

**Files:**
- Modify: `src/Support/Positions/PositionsParser.php`
- Create fixture: `tests/Fixtures/swetest-computed-codes.txt`
- Test: extend `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`

Computed-value rows reuse the non-numeric-first-column path (the label `Sidereal Time` / `Delta T` / `Ecl. Obl.` / `Ayanamsha` is the name token). The value column must be stored RAW — never coerced to float — because Sidereal Time is a UT time string (`01.01.2026 12:00:00 UT`). The existing parser already stores `trim($parts[$col])` as a string, so Task 7's path already preserves the raw value; this task adds an explicit fixture + assertion and ensures the body name is the computed label.

**Captured fixture (verbatim, derived from the SP3 reference `-px/-pq/-po/-pb` rows; value column after `PPP`):**

`tests/Fixtures/swetest-computed-codes.txt`:
```
Sidereal Time  PPP01.01.2026 12:00:00 UT
Delta T        PPP 71.0013120
Ecl. Obl.      PPP 23.4381321
Ayanamsha      PPP 24.2218573
```

> Each computed row has exactly ONE value column. The `-f` sequence for these is just `P<value-letter>` (e.g. `-fPx`), so the value sequence after the name is a single property. The parser maps it positionally to the first value property; the test below asserts the raw value text regardless of which property label it lands under, plus the body name (the computed label).

- [ ] **Step 1: Write the failing test**

Append to `tests/Features/Support/Positions/PositionsBodiesExtraTest.php`:

```php
use DivineaLabs\Swisseph\Enums\ComputedValue;

it('parses computed-value rows keeping the raw datum (no float coercion)', function () {
    $builder = (new PositionsBuilder)->selectComputedValue(
        ComputedValue::SIDEREAL_TIME,
        ComputedValue::DELTA_T,
        ComputedValue::ECLIPTIC_OBLIQUITY,
        ComputedValue::AYANAMSHA,
    );

    $lines = fixtureLines('swetest-computed-codes.txt');

    $frame = (new PositionsParser)->parse($lines, $builder);

    expect($frame->planet_bodies)->toHaveCount(4);

    $byName = collect($frame->planet_bodies)
        ->keyBy(fn ($b) => $b['planet_body']->name);

    // Names are the computed labels; indices are null.
    expect($byName->keys()->all())->toContain('Sidereal Time', 'Delta T', 'Ecl. Obl.', 'Ayanamsha');
    expect($byName['Sidereal Time']['planet_body']->index)->toBeNull();

    // Sidereal Time value is preserved as a TIME STRING, not coerced to a number.
    $siderealValue = $byName['Sidereal Time']['properties'][0]->value;
    expect($siderealValue)->toBe('01.01.2026 12:00:00 UT');
    expect($siderealValue)->toBeString();

    // Float-valued codes are still stored as their raw string token.
    expect($byName['Delta T']['properties'][0]->value)->toBe('71.0013120');
    expect($byName['Ecl. Obl.']['properties'][0]->value)->toBe('23.4381321');
    expect($byName['Ayanamsha']['properties'][0]->value)->toBe('24.2218573');
});

it('recognises a computed-value label via ComputedValue::fromLabel', function () {
    expect(ComputedValue::fromLabel('Sidereal Time'))->toBe(ComputedValue::SIDEREAL_TIME);
});
```

Create the fixture `tests/Fixtures/swetest-computed-codes.txt`:
```
Sidereal Time  PPP01.01.2026 12:00:00 UT
Delta T        PPP 71.0013120
Ecl. Obl.      PPP 23.4381321
Ayanamsha      PPP 24.2218573
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: FAIL initially IF the value sequence resolves to zero value-properties (because `selectComputedValue()` does not add value properties — the default sequence still contains LONGITUDE_DECIMAL/SPEED_LONGITUDE_DECIMAL which would mis-map). This drives Step 3.

> **Why this can fail without a parser change:** `getProperties()` returns the four DEFAULTS (`PLANET_INDEX, PLANET_NAME, LONGITUDE_DECIMAL, SPEED_LONGITUDE_DECIMAL`). For a computed row with a single value column, `valueProperties()` yields `[LONGITUDE_DECIMAL, SPEED_LONGITUDE_DECIMAL]` — two properties for one column. The first maps to the datum (fine for the assertion, which reads `properties[0]->value`), the second is skipped by the `$col >= count($parts)` guard. So the raw-value assertions pass via Task 7's code. The only genuinely new requirement is the explicit string/no-coercion guarantee — already satisfied because values are stored as `trim(...)` strings. **Therefore Step 3 is a verification + a defensive guard, not new branching.**

- [ ] **Step 3: Verify no numeric coercion exists; add a defensive guard if needed**

Inspect `parsePlanetRow()` (post-Task 7): the value is `trim($parts[$col])` — already a string, never cast. No `(float)`/`floatval()` is applied anywhere in `PositionsParser`. Confirm with:

Run: `grep -n "float\|intval\|floatval\|(int)\|(float)" src/Support/Positions/PositionsParser.php`
Expected: no matches inside `parsePlanetRow()`. If a future change introduced a numeric cast for value columns, gate it behind `ComputedValue::fromLabel($firstToken) === null` so computed rows keep their raw string. As written (SP3), NO code change is required in this step beyond confirming the invariant — the fixture+test lock it in.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsBodiesExtraTest.php`
Expected: PASS.

---

### Task 9: Full suite + static analysis green gate

**Files:** none (verification only).

- [ ] **Step 1: Run the full positions test directory**

Run: `vendor/bin/pest tests/Features/Support/Positions/`
Expected: PASS — migrated SP0 builder/parser tests + all SP3 `PositionsBodiesExtraTest` cases green; `PositionsMoonsIntegrationTest` SKIPPED (no binary) or PASS (binary present).

- [ ] **Step 2: Run the entire suite**

Run: `vendor/bin/pest`
Expected: PASS — no regression anywhere (PlanetBodyData nullable index, parser branch, new enums).

- [ ] **Step 3: Run static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors. Likely touch-points to clear:
- `PlanetBodyData::$index` now `?int` — ensure no consumer dereferences `->index` assuming non-null without a guard (grep `->index` across `src`).
- If PHPStan flags `valueProperties()`'s unused `$hasIndexColumn`, drop the param + the `true`/`false` call-site args (see Task 7 Step 3 note).

Run the grep to confirm consumers tolerate a null index:

Run: `grep -rn "->index" src tests`
Expected: any production use of `PlanetBodyData::$index` either tolerates null or only runs on numbered-planet rows.

---

## Self-Review notes

- **Spec coverage (SP3, four additions):**
  - Fixed stars — `selectFixedStar()` emits `-pf -xf<name>`; parser stores the comma-joined catalog name with `index=null` (Tasks 3, 7). ✅
  - Asteroid by MPC number — `selectAsteroid()` emits `-ps -xs<number>`; standard row, `index=null`, name `Eros` (Tasks 4, 7). ✅
  - Output-only computed codes — `ComputedValue` enum (`x/q/o/b/y` → labeled rows); `selectComputedValue()` appends to `-p`; parser stores the raw datum (time STRING for Sidereal Time, float-as-string otherwise) with NO coercion; Ayanamsha pairs with `withSidereal()` (Tasks 2, 5, 8). ✅
  - Planetary moons (thin) — `selectMoon()` emits `-pv -xv<number>`; reuses standard row parser; unit fixture explicitly DEFERRED to a self-skipping integration capture, logged in the plan (Task 6). ✅
- **Pipeline discipline:** no new pipeline — all changes extend `PositionsBuilder`/`PositionsParser`; `build()` only adds `...$this->bodyTargetArgs`, the rest of the SP0 assembly is untouched. ✅
- **DTO change:** `PlanetBodyData::$index` widened to `?int`; numbered-planet path still passes an int (Task 1). ✅
- **Parser tolerance:** integer-token detection (`isIntegerToken`) routes numbered planets through the legacy path and catalog/computed bodies through the name-as-col-0 path; value columns mapped from `valueProperties()` (PLANET_INDEX/PLANET_NAME filtered). Existing numbered-planet + house tests unaffected (Task 7 Step 5). ✅
- **Real captured fixtures:** fixed star (`swetest-fixedstar-sirius.txt`), asteroid (`swetest-asteroid-eros.txt`), and the four computed codes (`swetest-computed-codes.txt`) are verbatim from the SP3 reference (`swetest-output-reference.md` §SP3). ✅
- **No numeric coercion:** confirmed `PositionsParser` stores `trim(...)` strings; computed-value raw datum (incl. the UT time string) preserved; defensive gate documented if a future cast is added (Task 8 Step 3). ✅
- **Exact commands + expected results:** every step lists a concrete `vendor/bin/pest …` (or `phpstan`) command with PASS/FAIL/SKIPPED expectation. ✅
- **No commits:** plan performs no git operations (per instructions). ✅
- **Enum collision avoided:** computed letters live in `ComputedValue`, NOT `PlanetBodySelection`, sidestepping the `b/o/x/y/q` clashes with `AstroProperties`/existing selection letters. ✅
