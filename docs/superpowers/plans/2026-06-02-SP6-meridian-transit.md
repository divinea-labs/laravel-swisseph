# SP6 — Meridian Transit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `meridianTransits()` pipeline to `laravel-swisseph` that exposes swetest's native `-metr` mode — computing the upper/southern (`mtransit`) and lower/northern (`itransit`) meridian transit times for a body at a geographic location, one line per day.

**Architecture:** Follow the SP0 per-pipeline convention. A new `Support/Meridian/` unit holds `MeridianTransitBuilder` (fluent, immutable-style, `use`s the shared `ResolvesSwissephEnvironment` trait, emits a `SwissephCommand` via `build()`) and `MeridianTransitParser` (stdout lines → DTOs, tolerant guard-and-continue). The terminal `get()` wires the executor + parser and returns a `MeridianTransitCollection`. A new `MeridianTransitData` readonly DTO and the collection wrapper live under `src/Data/`. `Swisseph` gains a `meridianTransits()` factory method; the facade gains a `@method` annotation; the service provider binds the new classes.

**Tech Stack:** PHP 8.3/8.4, Laravel 11/12/13, spatie/laravel-data, spatie/laravel-package-tools, Pest 4, Symfony Process, larastan/PHPStan.

**Dependencies:** This plan assumes SP0 is complete — specifically `Support/Concerns/ResolvesSwissephEnvironment` (trait), `SwissephExecutor::run(SwissephCommand, array $skipPrefixes = [])`, and `Swisseph` as a factory. If SP0 is not yet merged, the builder still works (it only needs the trait + executor); apply the `Swisseph`/facade/provider edits (Tasks 4–5) against whatever the current factory shape is.

**Conventions locked by SP0 (this plan follows them):**
- Pipeline lives in `src/Support/Meridian/` with `MeridianTransitBuilder.php` + `MeridianTransitParser.php`; DTOs under `src/Data/`; exceptions under `src/Exceptions/`.
- Builder `use`s `ResolvesSwissephEnvironment` and emits a `SwissephCommand` via `build()`.
- `Swisseph::meridianTransits()` returns a fresh builder resolved from the container.
- The parser receives stdout lines already stripped of the command-echo + `geo. long` header by `SwissephExecutor` via the builder's declared skip prefixes (`['./', 'geo. long']`).
- Package convention is `Carbon` (UTC), not `CarbonImmutable` (cf. `RiseSetEvent`).

---

## Output reference (ground truth — captured on the compiled binary)

From `docs/superpowers/specs/swetest-output-reference.md`, section "SP6 — Meridian transit".

`-metr -p0 -geopos21.0,52.2,100 -n2` (Sun, Warsaw) — one line per day, after the geo header:

```
geo. long 21.000000, lat 52.200000, alt 100.000000
mtransit  1.01.2026	  10:39:32.3    itransit  1.01.2026	  22:39:46.3
mtransit  2.01.2026	  10:40:00.3    itransit  2.01.2026	  22:40:14.1
```

- swetest prints the invoked command line as the FIRST stdout line (e.g. `./swetests -edir…`) → skip via prefix `./`.
- For geopos modes it prints a `geo. long …, lat …, alt …` header line → skip via prefix `geo. long`.
- Tabs and spaces are mixed → tokenize on `\s+`.
- Each data line: `mtransit <date> <time>` (upper/southern meridian) + `itransit <date> <time>` (lower/northern meridian). Times are UT. Dates are `d.m.Y` (note the leading-space single-digit day, e.g. ` 1.01.2026`, which is collapsed by `\s+` tokenization). Times are `H:i:s.u`.

After skip-prefix filtering + `\s+` tokenization, a data line yields exactly 6 tokens:
`['mtransit', '1.01.2026', '10:39:32.3', 'itransit', '1.01.2026', '22:39:46.3']`.

---

## File Structure

- Create: `src/Support/Meridian/MeridianTransitBuilder.php` — fluent builder; `use ResolvesSwissephEnvironment`; `build(): SwissephCommand`; terminal `get(): MeridianTransitCollection`.
- Create: `src/Support/Meridian/MeridianTransitParser.php` — `parse(array $lines, PlanetBody $body): MeridianTransitCollection`; one `MeridianTransitData` per `mtransit` line; tolerant skip.
- Create: `src/Data/MeridianTransitData.php` — readonly spatie Data: `body`, `upperTransitAt`, `lowerTransitAt`, `date`.
- Create: `src/Data/MeridianTransitCollection.php` — `all()`, `first()`.
- Create: `src/Exceptions/MeridianGeoPositionNotSetException.php` — thrown by `build()`/`get()` when `at()` was never called.
- Modify: `src/Swisseph.php` — add `meridianTransits(): MeridianTransitBuilder`.
- Modify: `src/Facades/Swisseph.php` — add `@method static … meridianTransits()`.
- Modify: `src/SwissephServiceProvider.php` — bind `MeridianTransitBuilder` + `MeridianTransitParser`.
- Create test: `tests/Features/Support/Meridian/MeridianTransitBuilderTest.php` — argument assertions (body, geopos required-throw, from/count, backward).
- Create test: `tests/Features/Support/Meridian/MeridianTransitParserTest.php` — fixture = the exact 2-line Sun/Warsaw block; field-by-field assertions + a self-skipping integration test.

---

### Task 1: DTO, collection, and exception

**Files:**
- Create: `src/Data/MeridianTransitData.php`
- Create: `src/Data/MeridianTransitCollection.php`
- Create: `src/Exceptions/MeridianGeoPositionNotSetException.php`

- [ ] **Step 1: Write the DTO**

`src/Data/MeridianTransitData.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use Spatie\LaravelData\Data;

class MeridianTransitData extends Data
{
    public function __construct(
        public readonly PlanetBody $body,
        public readonly Carbon $upperTransitAt,   // mtransit — upper/southern meridian (UTC)
        public readonly Carbon $lowerTransitAt,   // itransit — lower/northern meridian (UTC)
        public readonly string $date,              // 'Y-m-d' (UTC) of the upper transit
    ) {}
}
```

- [ ] **Step 2: Write the collection wrapper**

`src/Data/MeridianTransitCollection.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class MeridianTransitCollection extends Data
{
    public function __construct(
        /** @var array<int, MeridianTransitData> */
        public readonly array $transits,
    ) {}

    /**
     * All transits in order (one per day).
     *
     * @return MeridianTransitData[]
     */
    public function all(): array
    {
        return array_values($this->transits);
    }

    /**
     * First transit, or null if the window produced none.
     */
    public function first(): ?MeridianTransitData
    {
        return $this->all()[0] ?? null;
    }
}
```

- [ ] **Step 3: Write the exception**

`src/Exceptions/MeridianGeoPositionNotSetException.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

class MeridianGeoPositionNotSetException extends \RuntimeException
{
    public static function make(): self
    {
        return new self(
            'A geographic position is required for meridian transit calculation. '
            .'Call ->at($longitude, $latitude, $elevation) before ->get().'
        );
    }
}
```

- [ ] **Step 4: Sanity-check autoloading**

Run: `vendor/bin/pest --filter 'this filter matches nothing' 2>&1 | head -n 5`
Expected: no fatal "class not found" autoload errors surface (the suite boots). These classes have no behavior of their own yet; they are exercised by Tasks 2–3.

---

### Task 2: MeridianTransitParser

**Files:**
- Create: `src/Support/Meridian/MeridianTransitParser.php`
- Test: `tests/Features/Support/Meridian/MeridianTransitParserTest.php`

- [ ] **Step 1: Write the failing test (real captured fixture)**

`tests/Features/Support/Meridian/MeridianTransitParserTest.php`:

```php
<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\MeridianTransitCollection;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Support\Meridian\MeridianTransitParser;

/**
 * Real captured Sun/Warsaw block (`-metr -p0 -geopos21.0,52.2,100 -n2`),
 * AFTER SwissephExecutor has stripped the command-echo + `geo. long` header.
 * Mixed tabs/spaces preserved as captured.
 */
function metrFixtureLines(): array
{
    return [
        "mtransit  1.01.2026\t  10:39:32.3    itransit  1.01.2026\t  22:39:46.3",
        "mtransit  2.01.2026\t  10:40:00.3    itransit  2.01.2026\t  22:40:14.1",
    ];
}

it('parses two daily meridian-transit lines for the Sun', function () {
    $collection = (new MeridianTransitParser())->parse(metrFixtureLines(), PlanetBody::SUN);

    expect($collection)->toBeInstanceOf(MeridianTransitCollection::class);
    expect($collection->all())->toHaveCount(2);
});

it('maps the first line (1.01.2026) field by field', function () {
    $first = (new MeridianTransitParser())->parse(metrFixtureLines(), PlanetBody::SUN)->first();

    expect($first->body)->toBe(PlanetBody::SUN);
    expect($first->date)->toBe('2026-01-01');

    expect($first->upperTransitAt)->toBeInstanceOf(Carbon::class);
    expect($first->upperTransitAt->timezone->getName())->toBe('UTC');
    expect($first->upperTransitAt->format('Y-m-d H:i:s'))->toBe('2026-01-01 10:39:32');
    expect($first->upperTransitAt->micro)->toBe(300000);

    expect($first->lowerTransitAt->format('Y-m-d H:i:s'))->toBe('2026-01-01 22:39:46');
    expect($first->lowerTransitAt->micro)->toBe(300000);
});

it('maps the second line (2.01.2026) field by field', function () {
    $second = (new MeridianTransitParser())->parse(metrFixtureLines(), PlanetBody::SUN)->all()[1];

    expect($second->body)->toBe(PlanetBody::SUN);
    expect($second->date)->toBe('2026-01-02');
    expect($second->upperTransitAt->format('Y-m-d H:i:s'))->toBe('2026-01-02 10:40:00');
    expect($second->upperTransitAt->micro)->toBe(300000);
    expect($second->lowerTransitAt->format('Y-m-d H:i:s'))->toBe('2026-01-02 22:40:14');
    expect($second->lowerTransitAt->micro)->toBe(100000);
});

it('tolerantly skips lines that do not start with mtransit', function () {
    $lines = [
        'some unexpected header',
        "mtransit  1.01.2026\t  10:39:32.3    itransit  1.01.2026\t  22:39:46.3",
        'itransit  1.01.2026   22:39:46.3', // orphan line, not a record start
        '',
    ];

    $collection = (new MeridianTransitParser())->parse($lines, PlanetBody::SUN);

    expect($collection->all())->toHaveCount(1);
    expect($collection->first()->date)->toBe('2026-01-01');
});

it('returns an empty collection when no lines match', function () {
    $collection = (new MeridianTransitParser())->parse(['nothing here'], PlanetBody::SUN);

    expect($collection->all())->toBe([]);
    expect($collection->first())->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Meridian/MeridianTransitParserTest.php`
Expected: FAIL — class `MeridianTransitParser` not found.

- [ ] **Step 3: Implement the parser**

Mirrors `RiseParser` discipline: never throws, guard-and-continue, dd.mm.yyyy + HH:MM:SS[.f] → UTC Carbon.

`src/Support/Meridian/MeridianTransitParser.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Meridian;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\MeridianTransitCollection;
use DivineaLabs\Swisseph\Data\MeridianTransitData;
use DivineaLabs\Swisseph\Enums\PlanetBody;

final class MeridianTransitParser
{
    /**
     * Parse swetest -metr output into a collection of daily transits.
     *
     * Contract: this method never throws. All validation is guard-and-continue,
     * mirroring RiseParser. Lines not starting with `mtransit` are skipped.
     *
     * Expected token layout (after \s+ split):
     *   [0]=mtransit [1]=date(d.m.Y) [2]=time(H:i:s.u)
     *   [3]=itransit [4]=date(d.m.Y) [5]=time(H:i:s.u)
     *
     * @param  array<int, string>  $lines
     */
    public function parse(array $lines, PlanetBody $body): MeridianTransitCollection
    {
        $datePattern = '/^\d{1,2}\.\d{1,2}\.\d{4}$/';
        $timePattern = '/^\d{2}:\d{2}:\d{2}(\.\d+)?$/';

        $transits = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if (! str_starts_with($line, 'mtransit')) {
                continue;
            }

            $tokens = preg_split('/\s+/', $line) ?: [];

            if (count($tokens) < 6) {
                continue;
            }
            if ($tokens[3] !== 'itransit') {
                continue;
            }
            if (! preg_match($datePattern, $tokens[1]) || ! preg_match($datePattern, $tokens[4])) {
                continue;
            }
            if (! preg_match($timePattern, $tokens[2]) || ! preg_match($timePattern, $tokens[5])) {
                continue;
            }

            $upper = $this->parseUtcDateTime($tokens[1], $tokens[2]);
            $lower = $this->parseUtcDateTime($tokens[4], $tokens[5]);

            if ($upper === null || $lower === null) {
                continue;
            }

            $transits[] = new MeridianTransitData(
                body: $body,
                upperTransitAt: $upper,
                lowerTransitAt: $lower,
                date: $upper->format('Y-m-d'),
            );
        }

        return new MeridianTransitCollection(transits: $transits);
    }

    /**
     * Parse a d.m.Y date and HH:MM:SS[.f] time into a UTC Carbon.
     *
     * Returns null if parsing fails — caller skips the line. Never throws.
     */
    private function parseUtcDateTime(string $date, string $time): ?Carbon
    {
        $parts = explode('.', $time, 2);
        $hms = $parts[0];           // 'HH:MM:SS'
        $fracRaw = $parts[1] ?? '0';

        // Normalise fractional seconds to 6 digits: '3' → '300000', '1' → '100000'
        $micros = (int) str_pad(substr($fracRaw, 0, 6), 6, '0', STR_PAD_RIGHT);

        $dt = Carbon::createFromFormat('d.m.Y H:i:s', "{$date} {$hms}", 'UTC');

        if (! ($dt instanceof Carbon)) {
            return null;
        }

        return $dt->setMicroseconds($micros);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Meridian/MeridianTransitParserTest.php`
Expected: PASS (5 tests).

---

### Task 3: MeridianTransitBuilder

**Files:**
- Create: `src/Support/Meridian/MeridianTransitBuilder.php`
- Test: `tests/Features/Support/Meridian/MeridianTransitBuilderTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Features/Support/Meridian/MeridianTransitBuilderTest.php`:

```php
<?php

use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\MeridianGeoPositionNotSetException;
use DivineaLabs\Swisseph\Support\Meridian\MeridianTransitBuilder;

beforeEach(function () {
    config()->set('swisseph.executable', '/bin/swetest');
    config()->set('swisseph.ephemeris_dir', '/ephe');
    config()->set('swisseph.eph_options', []);
});

it('throws when no geographic position was set', function () {
    (new MeridianTransitBuilder())->build();
})->throws(MeridianGeoPositionNotSetException::class);

it('builds the default Sun command at a location for one day', function () {
    $command = (new MeridianTransitBuilder())
        ->at(21.0, 52.2, 100.0)
        ->from('2026-01-01')
        ->build();

    $args = $command->arguments;

    expect($args)->toContain('p0');                       // default body = Sun
    expect($args)->toContain('metr');                     // always emitted
    expect($args)->toContain('geopos21,52.2,100');        // fmt trims trailing zeros
    expect($args)->toContain('b01.01.2026');
    expect($args)->toContain('n1');                       // default count = 1
    expect($args)->not->toContain('bwd');
});

it('maps a chosen body to the -p token', function () {
    $command = (new MeridianTransitBuilder())
        ->forBody(PlanetBody::MOON)
        ->at(21.0, 52.2, 100.0)
        ->build();

    expect($command->arguments)->toContain('p1');
});

it('honours from() and count()', function () {
    $command = (new MeridianTransitBuilder())
        ->at(21.0, 52.2)
        ->from('2026-03-15')
        ->count(7)
        ->build();

    expect($command->arguments)->toContain('b15.03.2026');
    expect($command->arguments)->toContain('n7');
    expect($command->arguments)->toContain('geopos21,52.2,0'); // default elevation 0.0
});

it('emits bwd when backward() is set', function () {
    $command = (new MeridianTransitBuilder())
        ->at(21.0, 52.2, 100.0)
        ->backward()
        ->build();

    expect($command->arguments)->toContain('bwd');
});

it('uses the configured executable and ephemeris dir', function () {
    $command = (new MeridianTransitBuilder())
        ->at(21.0, 52.2, 100.0)
        ->build();

    expect($command->executable)->toBe('/bin/swetest');
    expect($command->arguments)->toContain('edir/ephe');
});
```

> Note: `geopos`/`edir`/`b`/`ut` token shapes come from the shared `ResolvesSwissephEnvironment` trait (SP0). The exact `edir` token (`edir/ephe`) matches the trait's `epheDirArg()` normalization; if the trait formats differently in your tree, adjust the asserted string to match `epheDirArg()` output (the test asserts the trait's real output, not a guess).

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Meridian/MeridianTransitBuilderTest.php`
Expected: FAIL — class `MeridianTransitBuilder` not found.

- [ ] **Step 3: Implement the builder**

`src/Support/Meridian/MeridianTransitBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Meridian;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\MeridianTransitCollection;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\MeridianGeoPositionNotSetException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

final class MeridianTransitBuilder
{
    use ResolvesSwissephEnvironment;

    private PlanetBody $body = PlanetBody::SUN;

    private bool $hasGeoPosition = false;

    private ?Carbon $windowStartUtc = null;

    private int $count = 1;

    private bool $searchBackward = false;

    public function __construct(
        private ?SwissephExecutor $executor = null,
        private ?MeridianTransitParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
    }

    /**
     * Body whose meridian transit is computed. Defaults to the Sun.
     */
    public function forBody(PlanetBody $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Geographic position (required). Emits geopos<lon>,<lat>,<elev>.
     */
    public function at(float $longitude, float $latitude, float $elevation = 0.0): self
    {
        $this->longitude = $longitude;
        $this->latitude = $latitude;
        $this->elevation = $elevation;
        $this->hasGeoPosition = true;

        return $this;
    }

    /**
     * Window start date. Emits b<d.m.Y> (interpreted as UTC).
     */
    public function from(Carbon|string $date): self
    {
        $this->windowStartUtc = $date instanceof Carbon
            ? $date->copy()->utc()
            : Carbon::parse($date, 'UTC');

        return $this;
    }

    /**
     * Number of daily frames (one transit line per day). Default 1. Emits n<count>.
     */
    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Reverse swetest search direction. Emits bwd.
     */
    public function backward(): self
    {
        $this->searchBackward = true;

        return $this;
    }

    /**
     * Build the CLI command. Throws if no geographic position was set.
     *
     * @throws MeridianGeoPositionNotSetException
     */
    public function build(): SwissephCommand
    {
        if (! $this->hasGeoPosition) {
            throw MeridianGeoPositionNotSetException::make();
        }

        $start = $this->windowStartUtc ?? Carbon::now('UTC')->startOfDay();

        $args = [
            $this->epheDirArg(),
            ...$this->ephOptionArgs(),
            'p'.$this->body->value,
            'metr',
            'geopos'.$this->fmt($this->longitude).','.$this->fmt($this->latitude).','.$this->fmt($this->elevation),
            'b'.$start->format('d.m.Y'),
            'n'.$this->count,
        ];

        if ($this->searchBackward) {
            $args[] = 'bwd';
        }

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $args,
        );
    }

    /**
     * Skip prefixes for the executor: command-echo (`./…`) and geo header.
     *
     * @return string[]
     */
    public function skipPrefixes(): array
    {
        return ['./', 'geo. long'];
    }

    /**
     * Execute and parse into a MeridianTransitCollection.
     */
    public function get(): MeridianTransitCollection
    {
        $command = $this->build();

        $lines = ($this->executor ?? app(SwissephExecutor::class))
            ->run($command, $this->skipPrefixes());

        return ($this->parser ?? app(MeridianTransitParser::class))
            ->parse($lines, $this->body);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Meridian/MeridianTransitBuilderTest.php`
Expected: PASS (6 tests).

---

### Task 4: Wire the factory, facade, and provider

**Files:**
- Modify: `src/Swisseph.php`
- Modify: `src/Facades/Swisseph.php`
- Modify: `src/SwissephServiceProvider.php`
- Test: `tests/Features/Support/Meridian/MeridianTransitBuilderTest.php` (append a factory assertion)

- [ ] **Step 1: Add the factory method to `Swisseph`**

In `src/Swisseph.php` (post-SP0 factory shape), add:

```php
use DivineaLabs\Swisseph\Support\Meridian\MeridianTransitBuilder;

// …

public function meridianTransits(): MeridianTransitBuilder
{
    return app(MeridianTransitBuilder::class);
}
```

> If SP0 is NOT yet merged and `Swisseph` is still the god-class, add the same method returning a fresh `app(MeridianTransitBuilder::class)` instance — the builder is fully self-contained and does not depend on the legacy constructor wiring.

- [ ] **Step 2: Add the facade `@method` annotation**

In `src/Facades/Swisseph.php`, add to the docblock:

```php
 * @method static \DivineaLabs\Swisseph\Support\Meridian\MeridianTransitBuilder meridianTransits()
```

- [ ] **Step 3: Bind the new classes in the provider**

In `src/SwissephServiceProvider.php`, inside `registeringPackage()`, add:

```php
$this->app->bind(\DivineaLabs\Swisseph\Support\Meridian\MeridianTransitBuilder::class);
$this->app->bind(\DivineaLabs\Swisseph\Support\Meridian\MeridianTransitParser::class);
```

- [ ] **Step 4: Append a factory assertion to the builder test**

Add to `tests/Features/Support/Meridian/MeridianTransitBuilderTest.php`:

```php
it('is exposed as a fresh builder via the Swisseph factory', function () {
    expect(\DivineaLabs\Swisseph\Facades\Swisseph::meridianTransits())
        ->toBeInstanceOf(MeridianTransitBuilder::class);

    $a = \DivineaLabs\Swisseph\Facades\Swisseph::meridianTransits()->at(10.0, 20.0);
    $b = \DivineaLabs\Swisseph\Facades\Swisseph::meridianTransits();

    // Fresh instance per call — no shared mutable state.
    expect($b)->not->toBe($a);
});
```

- [ ] **Step 5: Run the meridian tests**

Run: `vendor/bin/pest tests/Features/Support/Meridian/`
Expected: PASS (parser 5 + builder 6 + factory 1 = 12 tests).

---

### Task 5: Self-skipping integration test + green gate

**Files:**
- Modify: `tests/Features/Support/Meridian/MeridianTransitParserTest.php` (append integration test)

- [ ] **Step 1: Append the self-skipping integration test**

This drives the real binary end-to-end and self-skips when `swetest` is unavailable (same pattern as the existing integration tests). Append to `MeridianTransitParserTest.php`:

```php
it('computes Sun meridian transits against the real binary', function () {
    $exe = (string) config('swisseph.executable', '');

    if ($exe === '' || ! is_file($exe) || ! is_executable($exe)) {
        $this->markTestSkipped('swetest binary not available — skipping integration test.');
    }

    $collection = \DivineaLabs\Swisseph\Facades\Swisseph::meridianTransits()
        ->forBody(\DivineaLabs\Swisseph\Enums\PlanetBody::SUN)
        ->at(21.0, 52.2, 100.0)
        ->from('2026-01-01')
        ->count(2)
        ->get();

    expect($collection->all())->toHaveCount(2);

    $first = $collection->first();
    expect($first->body)->toBe(\DivineaLabs\Swisseph\Enums\PlanetBody::SUN);
    expect($first->date)->toBe('2026-01-01');
    // Upper (southern) transit near local noon; lower (northern) ~12h later.
    expect($first->upperTransitAt->lessThan($first->lowerTransitAt))->toBeTrue();
})->group('integration');
```

- [ ] **Step 2: Run the full meridian suite**

Run: `vendor/bin/pest tests/Features/Support/Meridian/`
Expected: PASS — 12 unit tests green; the integration test PASSES if a real `swetest` is configured, otherwise reports SKIPPED. No failures either way.

- [ ] **Step 3: Run the full suite (regression gate)**

Run: `vendor/bin/pest`
Expected: PASS — all existing tests still green; the new meridian tests added.

- [ ] **Step 4: Static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors. Fix any type issues the new files surface (e.g. nullable executor/parser, `preg_split` return-type narrowing).

---

## Self-Review notes

- **Spec coverage (SP6):**
  - `MeridianTransitBuilder` with `use ResolvesSwissephEnvironment`, `forBody()` (default Sun) → `-p<value>`, `at()` → `-geopos<lon>,<lat>,<elev>` (required; throws), `from()` → `-b<d.m.Y>`, `count()` (default 1) → `-n<count>`, `backward()` → `-bwd`, always emits `-metr`, terminal `get(): MeridianTransitCollection`, `skipPrefixes ['./', 'geo. long']`. ✅ (Task 3)
  - `MeridianTransitParser`: one line per day, 6-token `\s+` layout, `mtransit`=upper, `itransit`=lower, Carbon UTC, tolerant skip of non-`mtransit` lines. ✅ (Task 2)
  - DTO `MeridianTransitData` (spatie Data, readonly): `body`, `upperTransitAt`, `lowerTransitAt`, `date`. ✅ (Task 1)
  - `MeridianTransitCollection`: `all()`, `first()`. ✅ (Task 1)
  - Exception `MeridianGeoPositionNotSetException`. ✅ (Task 1)
  - Registration: provider binding + `Swisseph::meridianTransits()` + facade `@method`. ✅ (Task 4)
  - Tests: builder (body, geopos required-throw, from/count, backward) + parser (exact 2-line Sun/Warsaw fixture; body/upperTransitAt/lowerTransitAt/date for both lines) + self-skipping integration. ✅ (Tasks 2, 3, 5)
- **Fixture fidelity:** the parser fixture is the exact captured 2-line block from the output reference, with the real mixed-tab/space whitespace preserved (`\t` between date and time), and the `\s+` tokenization collapsing the leading-space single-digit days. Assertions cover both lines incl. fractional seconds (`.3` → 300000 micros, `.1` → 100000 micros). ✅
- **Convention consistency:** parser never throws (mirrors `RiseParser` guard-and-continue); `Carbon` UTC (not Immutable) per package convention; `fmt()`/`epheDirArg()`/`ephOptionArgs()` reused from the shared trait; fresh builder per factory call. ✅
- **SP0 dependency flagged:** trait + executor-skip + factory are SP0 deliverables; Task 4 Step 1 notes the fallback if SP0 is not yet merged. ✅
- **No commits:** this plan intentionally contains no `git`/commit steps. ✅
```