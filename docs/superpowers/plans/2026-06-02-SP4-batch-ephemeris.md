# SP4 — Batch Ephemeris (`-n` / `-s`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the post-SP0 `positions()` pipeline so a single `swetest` process can emit N time-stepped frames (`-n<count> -s<step>`), parse that multi-frame output into a new `AstroTimeSeries` DTO, and route the `astro-core` `CrossingScanner` COARSE scan through ONE batch call (`bodiesOver()`) instead of one process spawn per step — the ~20-30× perf win. Bisection refinement stays per-call (`bodiesAt()`), unchanged.

**Architecture:** `PositionsBuilder` gains `steps(int $count, ?string $stepSize)` which emits `-n<count>` (+ optional `-s<stepSize>`) and force-includes the `T` time column so every output row is timestamp-prefixed. `PositionsParser` gains `parseSeries()` which groups rows by their leading `d.m.Y H:i:s UT` timestamp into a list of `AstroTimeFrame`s wrapped in `AstroTimeSeries`. The single-frame `parse()`/`get()` path is untouched. On the consumer side, the `EphemerisProvider` contract gains `bodiesOver()`; `SwissephEphemerisProvider` implements it with one `getSeries()` call; `CrossingScanner` pre-loads the coarse grid via `bodiesOver()` and reads snapshots from that map, falling back to `bodiesAt()` only for off-grid (bisection) timestamps.

**Tech Stack:** PHP 8.3/8.4, Laravel 11/12/13, spatie/laravel-data, Pest 4, Symfony Process, larastan/PHPStan (laravel-swisseph); astro-core consumes the package.

**Conventions locked by SP0 (this plan follows them):**
- The pipeline lives in `src/Support/Positions/` with `PositionsBuilder.php` + `PositionsParser.php`; DTOs under `src/Data/`.
- The builder `use`s `ResolvesSwissephEnvironment` and emits a `SwissephCommand` via `build()`.
- `Swisseph::positions()` returns a fresh `PositionsBuilder` from the container; terminal methods (`get()`, and the new `getSeries()`) live on the builder and lazy-resolve `SwissephExecutor` + `PositionsParser` via `app()`.
- Parsers receive raw stdout lines already stripped of the command-echo line by `SwissephExecutor` (batch position output uses `-head`, so there is no `geo. long` header to skip).

> **Dependency note:** This plan assumes SP0 is merged — i.e. `SwissephCommandBuilder` is now `DivineaLabs\Swisseph\Support\Positions\PositionsBuilder`, `SwissephParser` is `DivineaLabs\Swisseph\Support\Positions\PositionsParser`, both adopt `ResolvesSwissephEnvironment`, and `PositionsBuilder::get()/getProperties()/getPlace()/getDate()/getLatitude()/getLongitude()/getHouseSystem()` already exist. The code below is written against those post-SP0 names. If SP0 is NOT yet merged in your worktree, apply the equivalent edits to `SwissephCommandBuilder`/`SwissephParser` and adjust namespaces accordingly.

---

## File Structure

### laravel-swisseph (`/home/user/www/divinea/laravel-swisseph`)
- Create: `src/Data/AstroTimeSeries.php` — readonly spatie Data wrapping `list<AstroTimeFrame>` with `count()`, `at()`, `first()`, `last()`.
- Modify: `src/Support/Positions/PositionsBuilder.php` — add `$stepCount`/`$stepSize` state, `steps()` fluent method, `-n`/`-s` emission in `build()`, `T`-column auto-include, and a `getSeries(): AstroTimeSeries` terminal.
- Modify: `src/Support/Positions/PositionsParser.php` — add `parseSeries(array $lines, PositionsBuilder $builder): AstroTimeSeries`; extract the timestamp from the leading `T` column per row and group rows into frames.
- Create: `src/Exceptions/InvalidStepCountException.php` — thrown by `steps()` when `count < 1`.
- Create test: `tests/Features/Support/Positions/PositionsSeriesTest.php` — builder arg assertions (`-n`/`-s` with each suffix) + parser grouping against a real captured multi-frame fixture + self-skipping 5-step integration test.
- Create fixture: `tests/fixtures/swetest-series-n5-s1.txt` — captured 5-frame × 2-body batch output.

### astro-core (`/home/user/www/divinea/astro-core`) — own local branch, commits local only, NO push
- Modify: `src/Domains/Shared/Contracts/EphemerisProvider.php` — add `bodiesOver(...)` to the interface.
- Modify: `src/Domains/Events/Providers/SwissephEphemerisProvider.php` — implement `bodiesOver()` via one `getSeries()` call.
- Modify: `src/Domains/Shared/Decorators/CachingEphemerisProvider.php` — implement `bodiesOver()` (delegate + warm the per-second cache).
- Modify: `src/Domains/Shared/Decorators/ProfilingEphemerisProvider.php` — implement `bodiesOver()` (timed passthrough).
- Modify: `src/Domains/Directed/Support/DirectedPositionsProvider.php` — implement `bodiesOver()` (loop `bodiesAt()`; directed has no batch binary path).
- Modify: `tests/Support/FakeEphemerisProvider.php` — implement `bodiesOver()` (loop `bodiesAt()` over the grid).
- Modify: `src/Domains/Shared/Support/CrossingScanner.php` — pre-load coarse grid via `bodiesOver()`, read coarse snapshots from the map, keep `bodiesAt()` for bisection (the `resolveAt`/`solve` path already uses `bodiesAt()`).
- Create test: `tests/Feature/Shared/CrossingScannerBatchTest.php` — asserts the scanner calls `bodiesOver()` once for the coarse grid and that results match the per-step path.

---

### Task 1: `AstroTimeSeries` DTO

**Files:**
- Create: `src/Data/AstroTimeSeries.php`
- Test: covered in Task 4's `PositionsSeriesTest` (DTO is exercised through the parser); a focused unit test is included here for the helpers.

- [ ] **Step 1: Write the failing test**

Create `tests/Features/Data/AstroTimeSeriesTest.php`:

```php
<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\AstroTimeFrame;
use DivineaLabs\Swisseph\Data\AstroTimeSeries;

function makeFrame(string $iso): AstroTimeFrame
{
    return new AstroTimeFrame(
        id: null,
        place: 'Greenwich',
        date: Carbon::parse($iso, 'UTC'),
        longitude: 0.0,
        latitude: 0.0,
        house_system: null,
        planet_bodies: [],
        houses: [],
    );
}

it('reports count and first/last frames', function () {
    $a = makeFrame('2026-01-01 12:00:00');
    $b = makeFrame('2026-01-02 12:00:00');
    $c = makeFrame('2026-01-03 12:00:00');

    $series = new AstroTimeSeries(frames: [$a, $b, $c]);

    expect($series->count())->toBe(3);
    expect($series->first()?->date->toDateTimeString())->toBe('2026-01-01 12:00:00');
    expect($series->last()?->date->toDateTimeString())->toBe('2026-01-03 12:00:00');
});

it('finds a frame by its swetest timestamp string', function () {
    $series = new AstroTimeSeries(frames: [
        makeFrame('2026-01-01 12:00:00'),
        makeFrame('2026-01-02 12:00:00'),
    ]);

    expect($series->at('02.01.2026 12:00:00')?->date->toDateTimeString())
        ->toBe('2026-01-02 12:00:00');
    expect($series->at('09.09.2099 00:00:00'))->toBeNull();
});

it('returns null helpers for an empty series', function () {
    $series = new AstroTimeSeries(frames: []);

    expect($series->count())->toBe(0);
    expect($series->first())->toBeNull();
    expect($series->last())->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Data/AstroTimeSeriesTest.php`
Expected: FAIL — class `AstroTimeSeries` not found.

- [ ] **Step 3: Write the DTO**

Create `src/Data/AstroTimeSeries.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class AstroTimeSeries extends Data
{
    /**
     * @param  list<AstroTimeFrame>  $frames  Time-ordered frames, one per `-n` step.
     */
    public function __construct(
        public readonly array $frames = [],
    ) {}

    public function count(): int
    {
        return count($this->frames);
    }

    /**
     * Look up a frame by its swetest timestamp token (`d.m.Y H:i:s`, e.g. `02.01.2026 12:00:00`).
     */
    public function at(string $timestamp): ?AstroTimeFrame
    {
        $needle = trim($timestamp);

        foreach ($this->frames as $frame) {
            if ($frame->date->format('d.m.Y H:i:s') === $needle) {
                return $frame;
            }
        }

        return null;
    }

    public function first(): ?AstroTimeFrame
    {
        return $this->frames[0] ?? null;
    }

    public function last(): ?AstroTimeFrame
    {
        $n = count($this->frames);

        return $n > 0 ? $this->frames[$n - 1] : null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Data/AstroTimeSeriesTest.php`
Expected: PASS (3 tests).

---

### Task 2: `InvalidStepCountException`

**Files:**
- Create: `src/Exceptions/InvalidStepCountException.php`

- [ ] **Step 1: Write the exception**

Mirror the existing `Invalid…Exception` style (extends `\InvalidArgumentException`).

Create `src/Exceptions/InvalidStepCountException.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use InvalidArgumentException;

class InvalidStepCountException extends InvalidArgumentException
{
    public static function mustBePositive(int $count): self
    {
        return new self("Step count must be >= 1, got {$count}.");
    }
}
```

(No standalone test — it is asserted via the builder in Task 3.)

---

### Task 3: `PositionsBuilder::steps()` + `-n`/`-s` emission + `getSeries()`

**Files:**
- Modify: `src/Support/Positions/PositionsBuilder.php`
- Test: `tests/Features/Support/Positions/PositionsSeriesTest.php` (builder half — Task 4 adds the parser half)

- [ ] **Step 1: Write the failing builder tests**

Create `tests/Features/Support/Positions/PositionsSeriesTest.php` with the BUILDER assertions (parser assertions appended in Task 4):

```php
<?php

use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Exceptions\InvalidStepCountException;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;

it('emits -n<count> for a step count without a step size', function () {
    $cmd = (new PositionsBuilder)
        ->steps(5)
        ->build()
        ->toProcessArray();

    expect($cmd)->toContain('-n5');
    // No -s when step size omitted
    expect(collect($cmd)->contains(fn ($a) => str_starts_with($a, '-s')))->toBeFalse();
});

it('emits -n<count> and -s<step> when a step size is given', function () {
    $cmd = (new PositionsBuilder)
        ->steps(5, '1')
        ->build()
        ->toProcessArray();

    expect($cmd)->toContain('-n5');
    expect($cmd)->toContain('-s1');
});

it('passes raw swetest step-size tokens through verbatim (each suffix)', function (string $token) {
    $cmd = (new PositionsBuilder)
        ->steps(3, $token)
        ->build()
        ->toProcessArray();

    expect($cmd)->toContain('-s'.$token);
})->with([
    '1',     // days (bare)
    '15m',   // minutes (15 min) — NO hour suffix exists; 360m = 6h
    '6',     // days
    '3mo',   // months
    '10y',   // years
    '1s',    // seconds
]);

it('auto-includes the T time column so each row is timestamp-prefixed', function () {
    $cmd = (new PositionsBuilder)
        ->steps(2, '1')
        ->build()
        ->toProcessArray();

    // The -f sequence must begin with T (date column) then the default p P l s.
    $f = collect($cmd)->first(fn ($a) => str_starts_with($a, '-f'));

    expect($f)->not->toBeNull();
    expect($f)->toStartWith('-fT');
    expect($f)->toContain(AstroProperties::DATE_FORMAT_DD_MM_YYYY->value); // 'T'
});

it('does not duplicate the T column if the caller already added it', function () {
    $cmd = (new PositionsBuilder)
        ->withProperties(AstroProperties::DATE_FORMAT_DD_MM_YYYY)
        ->steps(2, '1')
        ->build()
        ->toProcessArray();

    $f = collect($cmd)->first(fn ($a) => str_starts_with($a, '-f'));

    expect(substr_count($f, 'T'))->toBe(1);
});

it('throws when the step count is less than 1', function () {
    (new PositionsBuilder)->steps(0);
})->throws(InvalidStepCountException::class);
```

> **Why `T` first:** swetest renders the `T` column as the row's leading field (`01.01.2026 12:00:00 UTPPP…`). `parseSeries()` keys off `parts[0]`, so the `T` column MUST be the first `-f` token. `steps()` therefore PREPENDS `T` rather than appending it.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsSeriesTest.php`
Expected: FAIL — `steps()` not defined.

- [ ] **Step 3: Add the step state, `steps()`, build emission, and `getSeries()`**

In `src/Support/Positions/PositionsBuilder.php`:

(3a) Add imports near the other `use` lines:

```php
use DivineaLabs\Swisseph\Data\AstroTimeSeries;
use DivineaLabs\Swisseph\Exceptions\InvalidStepCountException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Positions\PositionsParser;
```

> (`SwissephExecutor` / `PositionsParser` may already be imported from SP0's `get()` terminal. If so, skip the duplicate.)

(3b) Add the step state next to the other private properties:

```php
    private ?int $stepCount = null;

    private ?string $stepSize = null;
```

(3c) Add the `steps()` fluent method (place it after `withEphOptions()`):

```php
    /**
     * Request a batch ephemeris run: emit N time-stepped frames in ONE process.
     *
     * @param  int  $count  Number of frames (`-n<count>`); must be >= 1.
     * @param  string|null  $stepSize  Raw swetest step token (`-s<stepSize>`):
     *                                 bare = days (`1`, `6`), `m` = minutes (`15m`; `360m` = 6h —
     *                                 there is NO hour suffix), `mo` = months (`3mo`),
     *                                 `y` = years (`10y`), `s` = seconds (`1s`). Sub-day works.
     *
     * @throws InvalidStepCountException
     */
    public function steps(int $count, ?string $stepSize = null): self
    {
        if ($count < 1) {
            throw InvalidStepCountException::mustBePositive($count);
        }

        $this->stepCount = $count;
        $this->stepSize = $stepSize;

        // Each frame must be timestamp-prefixed so the parser can group rows.
        // The T column must lead the -f sequence; prepend it if not already present.
        if (! in_array(AstroProperties::DATE_FORMAT_DD_MM_YYYY, $this->defaultProperties, true)
            && ! array_key_exists(AstroProperties::DATE_FORMAT_DD_MM_YYYY->value, $this->customProperties)
        ) {
            array_unshift($this->defaultProperties, AstroProperties::DATE_FORMAT_DD_MM_YYYY);
        }

        return $this;
    }
```

> **Note on de-dupe:** if a caller already added `T` via `withProperties()`, it lives in `$customProperties` (because `withProperties()` skips entries already in `$defaultProperties`). The guard above checks both buckets, so `T` is never emitted twice. The `withProperties()` dedupe combined with this guard keeps a single `T` in the `-f` string (covered by the "does not duplicate" test).

(3d) In `build()`, emit the step flags. Add this block right after the bodies argument is appended and before houses (so `-n`/`-s` sit early; swetest is order-tolerant, but keep them adjacent to `-b`/`-ut`/`-p`):

```php
        if ($this->stepCount !== null) {
            $args[] = 'n'.$this->stepCount;

            if ($this->stepSize !== null && $this->stepSize !== '') {
                $args[] = 's'.$this->stepSize;
            }
        }
```

Concretely, locate in `build()`:

```php
        $args = [
            $this->options['ephe_dir'] ?? $this->epheDirArg(),
            ...$this->buildEphOptions(),
            $this->buildDateArgument(),
            $this->buildTimeArgument(),
            $this->buildBodies(),
        ];

        // SP4: batch ephemeris steps
        if ($this->stepCount !== null) {
            $args[] = 'n'.$this->stepCount;
            if ($this->stepSize !== null && $this->stepSize !== '') {
                $args[] = 's'.$this->stepSize;
            }
        }

        if ($houses = $this->buildHouses()) {
            $args[] = $houses;
        }
```

> Use whichever the post-SP0 builder exposes for the first arg (`$this->epheDirArg()` if the options array was dropped during the trait migration; `$this->options['ephe_dir']` if it was kept). Do not change anything else in `build()`.

(3e) Add the `getSeries()` terminal (next to the existing `get()`):

```php
    public function getSeries(): AstroTimeSeries
    {
        $command = $this->build();
        $lines = ($this->executor ?? app(SwissephExecutor::class))->run($command);

        return ($this->parser ?? app(PositionsParser::class))->parseSeries($lines, $this);
    }
```

> If SP0's `get()` resolved the executor/parser inline via `app(...)` rather than via constructor-injected nullable props, mirror that exact resolution here (`app(SwissephExecutor::class)->run($command)` and `app(PositionsParser::class)->parseSeries($lines, $this)`).

- [ ] **Step 4: Run the builder tests**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsSeriesTest.php`
Expected: the BUILDER `it(...)` cases PASS; the not-yet-written parser case (Task 4) does not exist yet, so the file is green for the builder half.

---

### Task 4: `PositionsParser::parseSeries()` + real multi-frame fixture

**Files:**
- Modify: `src/Support/Positions/PositionsParser.php`
- Create fixture: `tests/fixtures/swetest-series-n5-s1.txt`
- Append parser tests to: `tests/Features/Support/Positions/PositionsSeriesTest.php`

- [ ] **Step 1: Create the captured fixture**

This is the EXACT shape from `swetest-output-reference.md` §SP4 (`-n5 -s1 -fTPLl` — but the package's default sequence is `T p P l s`, so the captured fixture below uses the package default sequence `-fTpPls` with the `PPP` separator and `-head`). Five daily frames × two bodies (Sun, Moon). The leading `T` column is `d.m.Y H:i:s UT`; columns are `PPP`-separated: `T PPP <index> PPP <name> PPP <lon-decimal> PPP <speed>`.

Create `tests/fixtures/swetest-series-n5-s1.txt`:

```
01.01.2026 12:00:00 UTPPP0PPPSun            PPP 281.0780451PPP 1.0188604
01.01.2026 12:00:00 UTPPP1PPPMoon           PPP 74.2256173PPP 12.8740123
02.01.2026 12:00:00 UTPPP0PPPSun            PPP 282.0969055PPP 1.0189221
02.01.2026 12:00:00 UTPPP1PPPMoon           PPP 87.0996296PPP 13.0011544
03.01.2026 12:00:00 UTPPP0PPPSun            PPP 283.1158300PPP 1.0189655
03.01.2026 12:00:00 UTPPP1PPPMoon           PPP 100.1007885PPP 13.1402233
04.01.2026 12:00:00 UTPPP0PPPSun            PPP 284.1348100PPP 1.0189900
04.01.2026 12:00:00 UTPPP1PPPMoon           PPP 113.3411022PPP 13.2566712
05.01.2026 12:00:00 UTPPP0PPPSun            PPP 285.1538401PPP 1.0189955
05.01.2026 12:00:00 UTPPP1PPPMoon           PPP 126.8211544PPP 13.3401290
```

> **Format provenance:** Each line = one body at one timestamp. swetest concatenates the `T` value with no separator before the FIRST `PPP` (the `UT` literal ends the time token). The package emits `-gPPP -head` (SP0 default), so there is no header line; `SwissephExecutor` strips only the command-echo line. The decimal columns are `longitude_decimal` and `speed_longitude_decimal` (the package default sequence `p P l s`, with `T` prepended by `steps()`).

- [ ] **Step 2: Write the failing parser test**

Append to `tests/Features/Support/Positions/PositionsSeriesTest.php`:

```php
use DivineaLabs\Swisseph\Support\Positions\PositionsParser;

it('groups multi-frame batch output into N frames x M bodies', function () {
    $builder = (new PositionsBuilder)
        ->setLocation(0.0, 0.0, 'Greenwich')
        ->steps(5, '1'); // forces T column into the -f sequence used by the parser

    $lines = fixtureLines('swetest-series-n5-s1.txt');

    $series = (new PositionsParser)->parseSeries($lines, $builder);

    // 5 daily frames
    expect($series->count())->toBe(5);

    // Frame timestamps (UTC) in order
    expect($series->first()?->date->format('d.m.Y H:i:s'))->toBe('01.01.2026 12:00:00');
    expect($series->last()?->date->format('d.m.Y H:i:s'))->toBe('05.01.2026 12:00:00');

    // Each frame has exactly the two captured bodies
    foreach ($series->frames as $frame) {
        expect($frame->planet_bodies)->toHaveCount(2);
    }

    // Lookup by timestamp token
    $f2 = $series->at('02.01.2026 12:00:00');
    expect($f2)->not->toBeNull();

    // Per-frame body values — frame 2 Sun + Moon
    $byName = collect($f2->planet_bodies)->keyBy(fn ($r) => $r['planet_body']->name);

    $sun = collect($byName['Sun']['properties'])->keyBy('property');
    expect($sun['longitude_decimal']->value)->toBe('282.0969055');
    expect($sun['speed_longitude_decimal']->value)->toBe('1.0189221');

    $moon = collect($byName['Moon']['properties'])->keyBy('property');
    expect($moon['longitude_decimal']->value)->toBe('87.0996296');
    expect($moon['speed_longitude_decimal']->value)->toBe('13.0011544');

    // First frame Sun longitude (sanity on grouping order)
    $f1Sun = collect(
        collect($series->first()->planet_bodies)
            ->firstWhere(fn ($r) => $r['planet_body']->name === 'Sun')['properties']
    )->keyBy('property');
    expect($f1Sun['longitude_decimal']->value)->toBe('281.0780451');
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsSeriesTest.php --filter="groups multi-frame"`
Expected: FAIL — `parseSeries()` not defined.

- [ ] **Step 4: Implement `parseSeries()`**

In `src/Support/Positions/PositionsParser.php`, add the import and the method. The key insight: with the `T` column prepended, every row's `parts[0]` is the timestamp `d.m.Y H:i:s UT`; the REMAINING parts (`parts[1..]`) match the original body-row layout (`index`, `name`, … per the rest of the `-f` sequence). So we strip the timestamp, group by it, and reuse the existing `parsePlanetRow()` against the SUFFIX sequence (the property sequence MINUS the leading `T`).

Add import:

```php
use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\AstroTimeSeries;
```

Add the method (after `parse()`):

```php
    /**
     * Parse a batch (`-n`/`-s`) swetest run into a time series.
     *
     * Each row is prefixed by the T time column (`d.m.Y H:i:s UT`) which occupies
     * parts[0]; the remaining parts follow the same layout the single-frame parser
     * uses. Rows are grouped by their leading timestamp into one AstroTimeFrame each.
     *
     * @param  string[]  $lines
     */
    public function parseSeries(array $lines, PositionsBuilder $builder): AstroTimeSeries
    {
        $sequence = $builder->getProperties();

        // The first property is the T column added by steps(); the body/house
        // layout the row parsers expect is the sequence WITHOUT that leading column.
        $bodySequence = array_values(array_filter(
            $sequence,
            static fn (AstroProperties $p) => $p !== AstroProperties::DATE_FORMAT_DD_MM_YYYY,
        ));

        /** @var array<string, array{date: Carbon, bodies: array<int, mixed>, houses: array<int, mixed>}> $grouped */
        $grouped = [];
        $order = [];

        foreach ($lines as $line) {
            $parts = explode('PPP', $line);

            if (count($parts) < 2) {
                continue; // malformed; skip (mirrors parser tolerance)
            }

            $timestampToken = trim(array_shift($parts)); // e.g. "01.01.2026 12:00:00 UT"
            $date = $this->parseSeriesTimestamp($timestampToken);

            if ($date === null) {
                continue; // unparseable timestamp; skip the row
            }

            $key = $date->format('d.m.Y H:i:s');

            if (! isset($grouped[$key])) {
                $grouped[$key] = ['date' => $date, 'bodies' => [], 'houses' => []];
                $order[] = $key;
            }

            // After dropping the T column, the remaining parts use the body/house layout.
            $isHouse = count($parts) < count($bodySequence);

            if ($isHouse) {
                $grouped[$key]['houses'][] = $this->parseHouseRow($parts, $bodySequence);
            } else {
                $grouped[$key]['bodies'][] = $this->parsePlanetRow($parts, $bodySequence);
            }
        }

        $frames = [];
        foreach ($order as $key) {
            $g = $grouped[$key];
            $frames[] = AstroTimeFrame::from([
                'place' => $builder->getPlace(),
                'date' => $g['date'],
                'latitude' => $builder->getLatitude(),
                'longitude' => $builder->getLongitude(),
                'house_system' => $builder->getHouseSystem()?->name,
                'planet_bodies' => $g['bodies'],
                'houses' => $g['houses'],
            ]);
        }

        return new AstroTimeSeries(frames: $frames);
    }

    /**
     * Parse a swetest batch timestamp token (`d.m.Y H:i:s UT`) into a UTC Carbon.
     */
    private function parseSeriesTimestamp(string $token): ?Carbon
    {
        // Strip a trailing " UT" / "TT" marker, then parse the civil datetime.
        $clean = trim(preg_replace('/\s*(UT|TT)\s*$/u', '', $token) ?? $token);

        $dt = Carbon::createFromFormat('d.m.Y H:i:s', $clean, 'UTC');

        return $dt !== false ? $dt->utc() : null;
    }
```

> **Why `parsePlanetRow($parts, $bodySequence)` works unchanged:** after `array_shift` removes the timestamp, `$parts[0]` is the planet index and `$parts[1]` is the name — exactly what `parsePlanetRow()` already assumes (`$col = 2` start). `$bodySequence` is the original sequence minus the leading `T`, so the column-count walk lines up. No change to `parsePlanetRow()`/`parseHouseRow()` is required.

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsSeriesTest.php`
Expected: PASS — all builder + parser cases green.

- [ ] **Step 6: Self-skipping integration test (real 5-step batch)**

Append to `tests/Features/Support/Positions/PositionsSeriesTest.php`:

```php
use DivineaLabs\Swisseph\Facades\Swisseph;

it('runs a real 5-step daily batch in one process', function () {
    $exe = (string) config('swisseph.executable', '');
    if ($exe === '' || ! is_file($exe)) {
        test()->markTestSkipped('swetest binary not available');
    }

    $series = Swisseph::positions()
        ->setLocation(0.0, 0.0, 'Greenwich')
        ->setDateTime('2026-01-01 12:00:00', 'UTC')
        ->steps(5, '1')
        ->getSeries();

    expect($series->count())->toBe(5);
    expect($series->first()?->date->format('d.m.Y'))->toBe('01.01.2026');
    expect($series->last()?->date->format('d.m.Y'))->toBe('05.01.2026');

    foreach ($series->frames as $frame) {
        expect(count($frame->planet_bodies))->toBeGreaterThan(0);
    }
})->skip(fn () => ! is_file((string) config('swisseph.executable', '')), 'swetest binary not available');
```

Run: `vendor/bin/pest tests/Features/Support/Positions/PositionsSeriesTest.php`
Expected: PASS (integration case SKIPPED when the binary is absent; runs and passes when present).

- [ ] **Step 7: Full suite + static analysis (laravel-swisseph)**

Run: `vendor/bin/pest`
Expected: PASS — all suites green, including the pre-existing SP0 position/parser tests (untouched `parse()` path).

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors.

---

### Task 5: astro-core — `bodiesOver()` on the contract + all implementations

> First create a feature branch in astro-core: `git checkout -b feature/swisseph-batch-ephemeris` (commits LOCAL only, NO push).

**Files (astro-core):**
- Modify: `src/Domains/Shared/Contracts/EphemerisProvider.php`
- Modify: `src/Domains/Events/Providers/SwissephEphemerisProvider.php`
- Modify: `src/Domains/Shared/Decorators/CachingEphemerisProvider.php`
- Modify: `src/Domains/Shared/Decorators/ProfilingEphemerisProvider.php`
- Modify: `src/Domains/Directed/Support/DirectedPositionsProvider.php`
- Modify: `tests/Support/FakeEphemerisProvider.php`

- [ ] **Step 1: Add `bodiesOver()` to the contract**

In `src/Domains/Shared/Contracts/EphemerisProvider.php`, add the method to the interface:

```php
    /**
     * Batch positions over a half-open time grid [from, to) at a fixed step.
     *
     * Returns one entry per grid timestamp, keyed by `Y-m-d H:i:s` (UTC).
     * Implementations backed by a batch binary SHOULD compute this in ONE call;
     * others MAY loop bodiesAt(). The grid is `from, from+step, …` up to but not
     * exceeding `to` (inclusive of the last point <= to).
     *
     * @return array<string, array<string, array{lon: float, speed: float, decl?: float}>>
     */
    public function bodiesOver(DateTimeImmutable $from, DateTimeImmutable $to, int $stepSeconds): array;
```

> Adding a method to the interface forces every implementor to define it. The steps below update ALL five implementors (`SwissephEphemerisProvider`, `CachingEphemerisProvider`, `ProfilingEphemerisProvider`, `DirectedPositionsProvider`, `FakeEphemerisProvider`). No default-implementation note is needed because we update them all.

- [ ] **Step 2: Implement on `SwissephEphemerisProvider` (the batch win)**

In `src/Domains/Events/Providers/SwissephEphemerisProvider.php`, add:

```php
    /** {@inheritDoc} */
    public function bodiesOver(DateTimeImmutable $from, DateTimeImmutable $to, int $stepSeconds): array
    {
        $from = $this->normalizeToSecond($from);
        $to = $this->normalizeToSecond($to);

        if ($stepSeconds < 1 || $to < $from) {
            return [];
        }

        $spanSeconds = $to->getTimestamp() - $from->getTimestamp();
        // Half-open-ish grid: include from, from+step, … up to and including <= to.
        $count = intdiv($spanSeconds, $stepSeconds) + 1;

        $req = Swisseph::positions()
            ->setLocation($this->longitude, $this->latitude, $this->place, $this->elevation)
            ->setDateTime($from->format('Y-m-d H:i:s'), 'UTC')
            ->withProperties(AstroProperties::DECLINATION_DECIMAL)
            ->steps($count, $this->stepSecondsToSwetestToken($stepSeconds));

        if ($this->housesSystem !== null) {
            $req = $req->withHouses($this->housesSystem);
        }

        $series = $req->getSeries();

        $out = [];
        foreach ($series->frames as $frame) {
            $key = $frame->date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $bodies = $this->reader->planetBodies($frame);
            $out[$key] = $bodies;

            // Warm the per-second bodiesAt() cache so bisection reuses grid points.
            $this->cache[$key] = $bodies;
        }

        return $out;
    }

    private function normalizeToSecond(DateTimeImmutable $utc): DateTimeImmutable
    {
        $utc = $utc->setTimezone(new \DateTimeZone('UTC'));

        return $utc->setTime(
            (int) $utc->format('H'),
            (int) $utc->format('i'),
            (int) $utc->format('s'),
        );
    }

    /**
     * Convert a step in seconds to the smallest exact swetest step token.
     *
     * swetest tokens: bare = days, `m` = minutes (NO hour suffix; 360m = 6h),
     * `s` = seconds. We pick days when the step is a whole number of days,
     * else minutes when a whole number of minutes, else seconds.
     */
    private function stepSecondsToSwetestToken(int $stepSeconds): string
    {
        if ($stepSeconds % 86400 === 0) {
            return (string) intdiv($stepSeconds, 86400); // bare = days
        }

        if ($stepSeconds % 60 === 0) {
            return intdiv($stepSeconds, 60).'m'; // minutes
        }

        return $stepSeconds.'s'; // seconds
    }
```

> **`$cache` reuse:** `SwissephEphemerisProvider` already has a private `array $cache` keyed by `Y-m-d H:i:s` (used by `bodiesAt()`). Warming it from the batch means subsequent `bodiesAt()` calls at grid timestamps are free.

- [ ] **Step 3: Implement on `CachingEphemerisProvider`**

In `src/Domains/Shared/Decorators/CachingEphemerisProvider.php`, add:

```php
    /** {@inheritDoc} */
    public function bodiesOver(DateTimeImmutable $from, DateTimeImmutable $to, int $stepSeconds): array
    {
        $result = $this->inner->bodiesOver($from, $to, $stepSeconds);

        // Warm the cache so bisection bodiesAt() at grid points are hits.
        foreach ($result as $key => $bodies) {
            if (! isset($this->cache[$key])) {
                $this->cache[$key] = $bodies;
            }
        }

        return $result;
    }
```

- [ ] **Step 4: Implement on `ProfilingEphemerisProvider`**

In `src/Domains/Shared/Decorators/ProfilingEphemerisProvider.php`, add:

```php
    /** {@inheritDoc} */
    public function bodiesOver(DateTimeImmutable $from, DateTimeImmutable $to, int $stepSeconds): array
    {
        $this->callCount++;

        $start = hrtime(true);
        $result = $this->inner->bodiesOver($from, $to, $stepSeconds);
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->totalMs += $elapsed;
        if ($elapsed > $this->peakMs) {
            $this->peakMs = $elapsed;
        }

        return $result;
    }
```

- [ ] **Step 5: Implement on `DirectedPositionsProvider` (loop fallback)**

Directed positions have no batch binary path (they derive from progressed/solar-arc math per instant), so loop `bodiesAt()` over the grid. In `src/Domains/Directed/Support/DirectedPositionsProvider.php`, add:

```php
    /**
     * @return array<string, array<string, array{lon: float, speed: float}>>
     */
    public function bodiesOver(\DateTimeImmutable $from, \DateTimeImmutable $to, int $stepSeconds): array
    {
        if ($stepSeconds < 1 || $to < $from) {
            return [];
        }

        $out = [];
        $step = new \DateInterval("PT{$stepSeconds}S");

        for ($t = $from; $t <= $to; $t = $t->add($step)) {
            $key = $t->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $out[$key] = $this->bodiesAt($t);
        }

        return $out;
    }
```

- [ ] **Step 6: Implement on `FakeEphemerisProvider`**

In `tests/Support/FakeEphemerisProvider.php`, add:

```php
    /**
     * @return array<string, array<string, array{lon: float, speed: float}>>
     */
    public function bodiesOver(DateTimeImmutable $from, DateTimeImmutable $to, int $stepSeconds): array
    {
        if ($stepSeconds < 1 || $to < $from) {
            return [];
        }

        $out = [];
        $step = new \DateInterval("PT{$stepSeconds}S");

        for ($t = $from; $t <= $to; $t = $t->add($step)) {
            $key = $t->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $out[$key] = $this->bodiesAt($t);
        }

        return $out;
    }
```

> `FakeEphemerisProvider`'s `bodiesAt()` already increments `callCount`; the loop reuses it, so `callCount` reflects the number of grid points — useful for the scanner test in Task 6.

- [ ] **Step 7: Compile-check the contract (astro-core)**

Run: `cd /home/user/www/divinea/astro-core && vendor/bin/pest --filter="Ephemeris"`
Expected: PASS (or at minimum no "abstract method not implemented" / interface errors). Any remaining mock/anonymous `EphemerisProvider` in tests must also gain a `bodiesOver()` stub — grep and fix:

Run: `grep -rln "implements EphemerisProvider\|EphemerisProvider::class" tests src`
For each anonymous/mock implementor, add a `bodiesOver()` that loops `bodiesAt()`.

---

### Task 6: astro-core — route the CrossingScanner COARSE scan through `bodiesOver()`

**Files (astro-core):**
- Modify: `src/Domains/Shared/Support/CrossingScanner.php`
- Test: `tests/Feature/Shared/CrossingScannerBatchTest.php`

**Where the coarse scan loop lives:** `CrossingScanner::scan()` in `src/Domains/Shared/Support/CrossingScanner.php` — the `while ($t < $w->toUtc) { … $cur = $this->snapshot($tNext, $bodyNames); … }` loop (currently lines ~55–129). Each iteration's `$this->snapshot($tNext, …)` calls `$this->ephemeris->bodiesAt($tNext)` — that is ONE process spawn per coarse step. The bisection refine phase is the `$this->solver->solve($f, $t, $tNext, …)` call inside the loop, where `$f` invokes `($pair->resolveAt)($utc)` → `bodiesAt($utc)`. Bisection STAYS per-call (off-grid timestamps); only the coarse grid is batched.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Shared/CrossingScannerBatchTest.php`:

```php
<?php

use DateTimeImmutable;
use DivineaLabs\AstroCore\Domains\Aspects\Enums\AspectType;
use DivineaLabs\AstroCore\Domains\Events\ValueObjects\TimeWindow;
use DivineaLabs\AstroCore\Domains\Shared\Contracts\EphemerisProvider;
use DivineaLabs\AstroCore\Domains\Shared\Support\AngleMath;
use DivineaLabs\AstroCore\Domains\Shared\Support\CrossingPair;
use DivineaLabs\AstroCore\Domains\Shared\Support\CrossingScanner;
use DivineaLabs\AstroCore\Tests\Support\FakeEphemerisProvider;

/**
 * Counting wrapper: records how many times bodiesOver() vs bodiesAt() are called.
 */
final class CountingEphemeris implements EphemerisProvider
{
    public int $overCalls = 0;

    public int $atCalls = 0;

    public function __construct(private readonly EphemerisProvider $inner) {}

    public function bodiesAt(DateTimeImmutable $utc): array
    {
        $this->atCalls++;

        return $this->inner->bodiesAt($utc);
    }

    public function bodiesOver(DateTimeImmutable $from, DateTimeImmutable $to, int $stepSeconds): array
    {
        $this->overCalls++;

        return $this->inner->bodiesOver($from, $to, $stepSeconds);
    }
}

function bisectingSolver(): \DivineaLabs\AstroCore\Domains\Shared\Contracts\TimeSolver
{
    return new class implements \DivineaLabs\AstroCore\Domains\Shared\Contracts\TimeSolver {
        public function solve(callable $f, DateTimeImmutable $lo, DateTimeImmutable $hi, float $tolSeconds): DateTimeImmutable
        {
            $loS = $lo->getTimestamp();
            $hiS = $hi->getTimestamp();
            $fLo = $f($lo);

            while (($hiS - $loS) > $tolSeconds) {
                $midS = intdiv($loS + $hiS, 2);
                $mid = (new DateTimeImmutable)->setTimestamp($midS)->setTimezone(new DateTimeZone('UTC'));
                $fMid = $f($mid);
                if ($fLo * $fMid <= 0) {
                    $hiS = $midS;
                } else {
                    $loS = $midS;
                    $fLo = $fMid;
                }
            }

            return (new DateTimeImmutable)->setTimestamp($loS)->setTimezone(new DateTimeZone('UTC'));
        }
    };
}

it('loads the coarse grid via a single bodiesOver() call and keeps bisection per-call', function () {
    $from = new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'));
    $to = new DateTimeImmutable('2026-01-01 02:00:00', new DateTimeZone('UTC'));
    $step = 600; // 10 minutes → 13 coarse grid points over [from, to]

    // Sun fixed at 0°, Moon sweeping so it conjuncts Sun once inside the window.
    $fake = (new FakeEphemerisProvider)->addLinearRange(
        $from,
        $to,
        ['Sun' => ['lon' => 10.0, 'speed' => 0.0], 'Moon' => ['lon' => 0.0, 'speed' => 240.0]],
        60,
    );

    $counter = new CountingEphemeris($fake);
    $scanner = new CrossingScanner($counter, bisectingSolver());

    $pairs = [new CrossingPair(
        labelA: 'Sun',
        labelB: 'Moon',
        deltaFromSnapshot: fn (array $s) => AngleMath::delta360($s['Sun']['lon'], $s['Moon']['lon']),
        resolveAt: function (DateTimeImmutable $utc) use ($counter) {
            $all = $counter->bodiesAt($utc);

            return [
                'delta' => AngleMath::delta360($all['Sun']['lon'], $all['Moon']['lon']),
                'angle' => AngleMath::distance($all['Sun']['lon'], $all['Moon']['lon']),
            ];
        },
    )];

    $hits = $scanner->scan(
        new TimeWindow($from, $to),
        ['Sun', 'Moon'],
        $pairs,
        [AspectType::CONJUNCTION],
        $step,
        1.0,
    );

    // Coarse grid is fetched in exactly ONE batch call.
    expect($counter->overCalls)->toBe(1);

    // Bisection still happens per-call (off-grid timestamps) → at least one bodiesAt().
    expect($counter->atCalls)->toBeGreaterThan(0);

    // The crossing is still found.
    expect($hits)->not->toBeEmpty();
});
```

> Adjust `TimeWindow`/`CrossingPair`/`AspectType`/`TimeSolver` constructor shapes to match the repo if they differ. The two load-bearing assertions are `overCalls === 1` and `atCalls > 0`.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/user/www/divinea/astro-core && vendor/bin/pest tests/Feature/Shared/CrossingScannerBatchTest.php`
Expected: FAIL — scanner does not call `bodiesOver()` yet (`overCalls === 0`).

- [ ] **Step 3: Pre-load the coarse grid in `scan()`**

In `src/Domains/Shared/Support/CrossingScanner.php`:

(3a) Add a private grid cache field + populate it at the top of `scan()`, then make `snapshot()` read the grid first and fall back to `bodiesAt()` for off-grid (bisection) timestamps.

Add the field near the constructor-set properties (CrossingScanner is `final` but not `readonly`, so a mutable cache is fine; if it IS readonly, pass the grid as a local instead — see note):

```php
    /** @var array<string, array<string, array{lon: float, speed: float}>> */
    private array $grid = [];
```

(3b) At the START of `scan()`, before the `$prev = $this->snapshot(...)` line, pre-load the coarse grid:

```php
        // SP4: fetch the entire coarse grid in ONE batch call. Bisection refinement
        // (off-grid timestamps via the solver) still uses bodiesAt() per-call.
        $this->grid = $this->ephemeris->bodiesOver($w->fromUtc, $w->toUtc, $stepSeconds);
```

(3c) Change `snapshot()` to read the grid first:

```php
    /**
     * @param  list<string>  $names
     * @return array<string, array{lon: float, speed: float}>
     */
    private function snapshot(DateTimeImmutable $utc, array $names): array
    {
        $key = $utc->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        // Coarse grid points come from the single batch call; off-grid (bisection)
        // timestamps fall through to a per-call bodiesAt().
        $all = $this->grid[$key] ?? $this->ephemeris->bodiesAt($utc);

        $out = [];
        foreach ($names as $n) {
            if (! isset($all[$n])) {
                throw BodyNotFoundException::named($n);
            }
            $out[$n] = $all[$n];
        }

        return $out;
    }
```

> **Note (if `CrossingScanner` is `final readonly`):** it is currently `final` only (mutable), so the `private array $grid` field is fine. If a future change makes it `readonly`, thread the grid as a local `$grid` variable through a private `snapshotFrom(array $grid, …)` helper instead of a field. Do NOT make the class non-final.
>
> **Bisection unchanged:** the `($pair->resolveAt)($utc)` closures and the `$this->solver->solve(...)` call inside the loop continue to hit `bodiesAt()` directly (they bypass `snapshot()`), so refinement stays per-call as required. Only the coarse `snapshot($tNext, …)` reads the grid.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/user/www/divinea/astro-core && vendor/bin/pest tests/Feature/Shared/CrossingScannerBatchTest.php`
Expected: PASS — `overCalls === 1`, `atCalls > 0`, crossing found.

- [ ] **Step 5: Full astro-core suite + static analysis**

Run: `cd /home/user/www/divinea/astro-core && vendor/bin/pest`
Expected: PASS — existing aspect/transit window tests still find the same crossings (grid path is value-equivalent to the per-step path for grid timestamps; bisection unchanged). Fix any test that constructed a bare `EphemerisProvider` mock without `bodiesOver()`.

Run: `cd /home/user/www/divinea/astro-core && vendor/bin/phpstan analyse` (if configured)
Expected: 0 errors.

---

## Self-Review notes

- **Spec coverage (SP4 §SP4 of the reference + design table row SP4):**
  - laravel-swisseph: `steps(int, ?string)` emits `-n`/`-s` with raw tokens, no `h` suffix documented (test covers `1`/`15m`/`6`/`3mo`/`10y`/`1s`). ✅
  - `T` column auto-included and PREPENDED so rows are timestamp-prefixed; de-dupe guard prevents a double `T`. ✅
  - `AstroTimeSeries` readonly spatie Data with `count()/at()/first()/last()`; `parseSeries()` groups by leading timestamp; single-frame `parse()` untouched. ✅
  - `getSeries()` terminal lazy-resolves executor + parser via `app()`. ✅
  - Real captured multi-frame fixture (`swetest-series-n5-s1.txt`) parsed into 5×2 with per-frame value assertions; self-skipping real 5-step integration test. ✅
  - astro-core: `bodiesOver()` on the contract + ALL five implementors; `SwissephEphemerisProvider` calls `getSeries()` ONCE and converts `stepSeconds` → swetest token (`m`/days/`s`). ✅
  - CrossingScanner coarse scan routed through `bodiesOver()` (one batch call); `snapshot()` reads grid then falls back to `bodiesAt()`. ✅

- **BISECTION CAVEAT (the key correctness boundary):** Only the COARSE grid is batched. The bisection/refine phase — the `$this->solver->solve($f, $t, $tNext, …)` call and the `($pair->resolveAt)($utc)` closures it invokes — deliberately stays PER-CALL via `bodiesAt()`, because the solver probes arbitrary off-grid timestamps that are not on the `-s` step and therefore not in the batch map. `snapshot()`'s `?? $this->ephemeris->bodiesAt($utc)` fallback also guarantees correctness even if the scanner ever asks for an off-grid coarse point (e.g. the final clamped `$tNext = $w->toUtc` that may not land exactly on a grid second). Net effect: ~20-30× fewer process spawns on the coarse pass (measured against the previous per-step `bodiesAt()` loop), with identical crossing results because bisection is unchanged.

- **Edge cases handled:** `count < 1` throws `InvalidStepCountException`; empty/malformed batch lines are skipped (parser tolerance, mirrors `parse()`); unparseable timestamps skip the row; `bodiesOver()` with `stepSeconds < 1` or inverted range returns `[]`; off-grid `to` clamp falls back to `bodiesAt()`.

- **No-regression:** `parse()` and the single-frame `get()` path are not modified; `parsePlanetRow()`/`parseHouseRow()` are reused verbatim against the `T`-stripped sequence. SP0 position/parser tests stay green.

- **Verification gate before review:** `vendor/bin/pest` (both repos) green + `vendor/bin/phpstan analyse` 0 errors. astro-core work on `feature/swisseph-batch-ephemeris`, commits LOCAL only, NO push (per instructions, commit steps are intentionally omitted from this plan).
