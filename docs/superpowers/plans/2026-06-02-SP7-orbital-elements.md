# SP7 — Orbital Elements (`-orbel`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an `orbitalElements()` pipeline to `laravel-swisseph` that exposes swetest's native `-orbel` osculating orbital-element computation for a single body, returning a fully-typed `OrbitalElementsData` DTO.

**Architecture:** A new per-pipeline unit under `src/Support/OrbitalElements/`. `OrbitalElementsBuilder` `use`s the shared `ResolvesSwissephEnvironment` trait (from SP0) for executable/ephe-dir/eph-options, adds a required `forBody(PlanetBody)` selector emitting `-p<value>` and an optional `from(Carbon|string)` reference date emitting `-b<d.m.Y>`, and always emits `-orbel`. It builds a `SwissephCommand`, executes it (skipping the `./`-prefixed command-echo line), and hands the remaining lines to `OrbitalElementsParser`. The parser skips the header/context lines and maps the `key\tvalue` block onto the readonly `OrbitalElementsData` DTO. `Swisseph::orbitalElements()` returns a fresh builder.

**Tech Stack:** PHP 8.3/8.4, Laravel 11/12/13, spatie/laravel-data, spatie/laravel-package-tools, Pest 4, Symfony Process, larastan/PHPStan.

**Key reference-output facts (ground truth — `docs/superpowers/specs/swetest-output-reference.md`, "SP7 — Orbital elements"):**
- Invocation: `-orbel -p4 -n1` (Mars). `-orbel` uses **TT/ET** reference time; **there is no `-ut`** time argument for this mode. `from()` therefore emits only `-b<d.m.Y>` (the TT reference date) and nothing else.
- swetest echoes the invoked command line as the first stdout line, prefixed `./` (e.g. `./swetests …`) → skip with `skipPrefixes: ['./']`.
- Header/context lines to SKIP: `date (dmy)…`, `UT:…`, `TT:…`, `Epsilon…`, `Nutation…`, and the single body-position line (e.g. `Mars  282°41'…`). These are context, not elements.
- Element block is `key\tvalue` lines (tab-separated). `time pericenter` carries **two** values (a JD float and a civil date string) → capture both.

**Conventions locked by SP0 (this plan follows them):**
- Pipeline lives in `src/Support/OrbitalElements/` with `OrbitalElementsBuilder.php` + `OrbitalElementsParser.php`; DTO under `src/Data/`; exception under `src/Exceptions/`.
- Builder `use`s `ResolvesSwissephEnvironment` and emits a `SwissephCommand` via `build()`.
- `Swisseph::orbitalElements()` returns a fresh builder resolved from the container.
- Parser receives raw stdout lines already stripped of the command-echo line by `SwissephExecutor` via the builder's declared `skipPrefixes`.

> **Dependency:** this plan assumes SP0 is merged — i.e. `ResolvesSwissephEnvironment` exists, `SwissephExecutor::run(SwissephCommand, array $skipPrefixes = [])` supports skip-prefixes, and `Swisseph` is a sub-builder factory. If SP0 is not yet in place, complete it first.

---

## File Structure

- Create: `src/Data/OrbitalElementsData.php` — final readonly spatie Data DTO (19 fields).
- Create: `src/Exceptions/OrbitalElementsBodyNotSetException.php` — thrown by `get()`/`build()` when no body was set.
- Create: `src/Support/OrbitalElements/OrbitalElementsBuilder.php` — fluent builder, `use ResolvesSwissephEnvironment`.
- Create: `src/Support/OrbitalElements/OrbitalElementsParser.php` — `key\tvalue` block parser → `OrbitalElementsData`.
- Modify: `src/Swisseph.php` — add `orbitalElements(): OrbitalElementsBuilder`.
- Modify: `src/Facades/Swisseph.php` — add `@method` annotation.
- Modify: `src/SwissephServiceProvider.php` — bind the new builder + parser.
- Create test: `tests/Features/Support/OrbitalElements/OrbitalElementsBuilderTest.php` — argument assertions (body required-throw, `from`, emits `-orbel`).
- Create test: `tests/Features/Support/OrbitalElements/OrbitalElementsParserTest.php` — full field-by-field mapping against the real Mars fixture.
- Create test: `tests/Features/Support/OrbitalElements/OrbitalElementsIntegrationTest.php` — self-skipping binary integration test.

---

### Task 1: The DTO — `OrbitalElementsData`

**Files:**
- Create: `src/Data/OrbitalElementsData.php`

- [ ] **Step 1: Write the DTO**

Pure data carrier — no test of its own (it is exercised in full by the parser test in Task 4). It mirrors the `RiseSetEvent` style: a `final` class extending spatie `Data` with a readonly promoted constructor.

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use DivineaLabs\Swisseph\Enums\PlanetBody;
use Spatie\LaravelData\Data;

final class OrbitalElementsData extends Data
{
    public function __construct(
        public readonly PlanetBody $body,
        public readonly float $semiAxis,
        public readonly float $eccentricity,
        public readonly float $inclination,
        public readonly float $ascendingNode,
        public readonly float $argPericenter,
        public readonly float $pericenter,
        public readonly float $meanLongitude,
        public readonly float $meanAnomaly,
        public readonly float $eccentricAnomaly,
        public readonly float $trueAnomaly,
        public readonly float $timePericenterJd,
        public readonly string $timePericenterCivil,
        public readonly float $distPericenter,
        public readonly float $distApocenter,
        public readonly float $meanDailyMotion,
        public readonly float $siderealPeriodYears,
        public readonly float $tropicalPeriodYears,
        public readonly float $synodicCycleDays,
    ) {}
}
```

---

### Task 2: The exception — `OrbitalElementsBodyNotSetException`

**Files:**
- Create: `src/Exceptions/OrbitalElementsBodyNotSetException.php`

- [ ] **Step 1: Write the exception**

Follows the existing `Exceptions/` style (`final`, extends a relevant SPL exception, static-ish message). Thrown when a terminal/build call happens without `forBody()`.

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use LogicException;

final class OrbitalElementsBodyNotSetException extends LogicException
{
    public function __construct()
    {
        parent::__construct(
            'No body set for the orbital-elements query. Call forBody(PlanetBody $body) before build()/get().'
        );
    }
}
```

---

### Task 3: The builder — `OrbitalElementsBuilder`

**Files:**
- Create: `src/Support/OrbitalElements/OrbitalElementsBuilder.php`
- Test: `tests/Features/Support/OrbitalElements/OrbitalElementsBuilderTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\OrbitalElementsBodyNotSetException;
use DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsBuilder;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('throws when no body is set', function () {
    $builder = new OrbitalElementsBuilder;

    $builder->build();
})->throws(OrbitalElementsBodyNotSetException::class);

it('emits -orbel and the -p<value> body selector', function () {
    $builder = new OrbitalElementsBuilder;
    $builder->forBody(PlanetBody::MARS);

    $cmd = $builder->build()->toProcessArray();

    expect($cmd)->toContain('-orbel');
    expect($cmd)->toContain('-p4');
});

it('emits the -b reference date from from() in swetest d.m.Y format', function () {
    $builder = new OrbitalElementsBuilder;
    $builder->forBody(PlanetBody::MARS)->from('2026-01-01');

    $cmd = $builder->build()->toProcessArray();

    expect($cmd)->toContain('-b01.01.2026');
});

it('accepts a Carbon instance in from()', function () {
    $builder = new OrbitalElementsBuilder;
    $builder->forBody(PlanetBody::MARS)->from(Carbon::create(2024, 5, 8, 0, 0, 0, 'UTC'));

    $cmd = $builder->build()->toProcessArray();

    expect($cmd)->toContain('-b08.05.2024');
});

it('does NOT emit a -ut time argument (-orbel is TT/ET only)', function () {
    $builder = new OrbitalElementsBuilder;
    $builder->forBody(PlanetBody::MARS)->from('2026-01-01');

    $args = $builder->build()->arguments;

    expect($args)->each->not->toStartWith('ut');
});

it('returns the configured executable and ephemeris dir', function () {
    config()->set('swisseph.executable', '/bin/swetest');
    config()->set('swisseph.ephemeris_dir', '/srv/ephe');

    $builder = new OrbitalElementsBuilder;
    $builder->forBody(PlanetBody::MARS);

    $cmd = $builder->build();

    expect($cmd->executable)->toBe('/bin/swetest');
    expect($cmd->toProcessArray())->toContain('-edir/srv/ephe');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/OrbitalElements/OrbitalElementsBuilderTest.php`
Expected: FAIL — class `OrbitalElementsBuilder` not found.

- [ ] **Step 3: Write the builder**

The builder leans entirely on `ResolvesSwissephEnvironment` for executable, ephe-dir, eph-options and the `from()`-backing `setDateTime()` + `dateArg()`. `from()` is a thin alias over `setDateTime()` (date-only matters for `-b`). It does NOT emit `utTimeArg()` — `-orbel` is TT/ET-only. `get()` lazily resolves the executor + parser from the container so the builder remains constructible without arguments in unit tests.

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\OrbitalElements;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OrbitalElementsData;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\OrbitalElementsBodyNotSetException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

class OrbitalElementsBuilder
{
    use ResolvesSwissephEnvironment;

    /** Lines starting with these are dropped by the executor (the `./swetests …` command echo). */
    private const SKIP_PREFIXES = ['./'];

    private ?PlanetBody $body = null;

    private bool $dateExplicit = false;

    public function __construct(
        protected ?SwissephExecutor $executor = null,
        protected ?OrbitalElementsParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
    }

    /**
     * Select the body whose osculating orbital elements are computed (required).
     * Emits `-p<value>` (e.g. Mars → `-p4`).
     */
    public function forBody(PlanetBody $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set the TT/ET reference date. Emits `-b<d.m.Y>`.
     * `-orbel` uses Terrestrial Time — there is no `-ut` for this mode.
     */
    public function from(Carbon|string $date): static
    {
        $this->setDateTime($date);
        $this->dateExplicit = true;

        return $this;
    }

    public function build(): SwissephCommand
    {
        if ($this->body === null) {
            throw new OrbitalElementsBodyNotSetException;
        }

        $arguments = [
            $this->epheDirArg(),
            'orbel',
            'p'.$this->body->value,
        ];

        // Reference date (-b). No -ut: -orbel is TT/ET-only.
        if ($this->dateExplicit) {
            $arguments[] = $this->dateArg();
        }

        foreach ($this->ephOptionArgs() as $opt) {
            $arguments[] = $opt;
        }

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $arguments,
        );
    }

    public function getCliCommand(): string
    {
        return $this->build()->toCliString();
    }

    public function get(): OrbitalElementsData
    {
        $command = $this->build();

        $lines = ($this->executor ?? app(SwissephExecutor::class))
            ->run($command, self::SKIP_PREFIXES);

        return ($this->parser ?? app(OrbitalElementsParser::class))
            ->parse($lines, $this->body ?? PlanetBody::MARS);
    }
}
```

> Note: `$this->body` is guaranteed non-null in `get()` because `build()` (called first) throws otherwise; the `?? PlanetBody::MARS` is an unreachable static-analysis appeaser. If PHPStan still complains, replace with `assert($this->body !== null)` after `build()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/OrbitalElements/OrbitalElementsBuilderTest.php`
Expected: PASS (7 tests).

---

### Task 4: The parser — `OrbitalElementsParser`

**Files:**
- Create: `src/Support/OrbitalElements/OrbitalElementsParser.php`
- Test: `tests/Features/Support/OrbitalElements/OrbitalElementsParserTest.php`

- [ ] **Step 1: Write the failing test (fixture = the EXACT captured Mars block)**

The fixture is the verbatim `-orbel -p4 -n1` block from the reference doc, including the `./` command-echo line and all skipped header/context lines, fed as an array of lines exactly as `SwissephExecutor` would after splitting on newlines — except the parser is handed lines AFTER the executor already removed the `./`-prefixed echo. To prove the parser is self-sufficiently tolerant, the test still includes the header/context lines (`date (dmy)`, `UT:`, `TT:`, `Epsilon`, `Nutation`, and the `Mars …` position line) so the parser must skip them itself.

```php
<?php

use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsParser;

/**
 * Verbatim swetest `-orbel -p4 -n1` (Mars) output, minus the `./swetests …`
 * command-echo line (already stripped by SwissephExecutor via skipPrefixes).
 * Tab characters in the key\tvalue block are preserved exactly.
 */
function marsOrbelLines(): array
{
    return [
        "date (dmy) 1.1.2026 greg.   0:00:00 TT\t\tversion 2.10.02",
        'UT:  2461041.499178233     delta t: 71.000655 sec',
        'TT:  2461041.500000000',
        "Epsilon (t/m)     23°26'17.2935   23°26' 9.2285",
        "Nutation           0° 0' 5.4212    0° 0' 8.0650",
        "Mars             282°41'15.1684   -0°53'27.9922    2.410699395    0°45'58.9047",
        "semiaxis         \t1.523694",
        "eccentricity     \t0.093485",
        "inclination      \t1.847499",
        "asc. node       \t49.482984",
        "arg. pericenter  \t286.623510",
        "pericenter       \t336.106494",
        "mean longitude   \t291.928275",
        "mean anomaly     \t315.821781",
        "ecc. anomaly     \t311.830723",
        "true anomaly     \t307.703699",
        "time pericenter  \t2460438.822970  8.05.2024,  07:45:04.6",
        "dist. pericenter \t1.381252",
        "dist. apocenter  \t1.666136",
        "mean daily motion\t0.524032",
        "sid. period (y)  \t1.880890",
        "trop. period (y) \t1.880829",
        "synodic cycle (d)\t779.936372",
    ];
}

it('parses every orbital-element field for Mars', function () {
    $data = (new OrbitalElementsParser)->parse(marsOrbelLines(), PlanetBody::MARS);

    expect($data->body)->toBe(PlanetBody::MARS);
    expect($data->semiAxis)->toBe(1.523694);
    expect($data->eccentricity)->toBe(0.093485);
    expect($data->inclination)->toBe(1.847499);
    expect($data->ascendingNode)->toBe(49.482984);
    expect($data->argPericenter)->toBe(286.623510);
    expect($data->pericenter)->toBe(336.106494);
    expect($data->meanLongitude)->toBe(291.928275);
    expect($data->meanAnomaly)->toBe(315.821781);
    expect($data->eccentricAnomaly)->toBe(311.830723);
    expect($data->trueAnomaly)->toBe(307.703699);
    expect($data->timePericenterJd)->toBe(2460438.822970);
    expect($data->timePericenterCivil)->toBe('8.05.2024,  07:45:04.6');
    expect($data->distPericenter)->toBe(1.381252);
    expect($data->distApocenter)->toBe(1.666136);
    expect($data->meanDailyMotion)->toBe(0.524032);
    expect($data->siderealPeriodYears)->toBe(1.880890);
    expect($data->tropicalPeriodYears)->toBe(1.880829);
    expect($data->synodicCycleDays)->toBe(779.936372);
});

it('ignores unknown keys and header/context lines', function () {
    $lines = array_merge(marsOrbelLines(), [
        "some future key  \t9.999999", // unknown → ignored
    ]);

    $data = (new OrbitalElementsParser)->parse($lines, PlanetBody::MARS);

    expect($data->semiAxis)->toBe(1.523694);
    expect($data->synodicCycleDays)->toBe(779.936372);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/OrbitalElements/OrbitalElementsParserTest.php`
Expected: FAIL — class `OrbitalElementsParser` not found.

- [ ] **Step 3: Write the parser**

The parser splits each line on the FIRST tab into `key` + remainder, trims the key, and dispatches via a `key → field` map. Header/context lines (`date (dmy)`, `UT:`, `TT:`, `Epsilon`, `Nutation`, and the body-position line) contain no leading-key tab in the element sense and/or are not in the key map, so they are naturally skipped. `time pericenter` is special-cased: its value has a leading JD float followed by a civil-date string — split off the first whitespace-delimited token as the JD, keep the rest (trimmed) as the civil string. Unknown keys are ignored. Like `RiseParser`, the parser is guard-and-continue and never throws on malformed lines.

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\OrbitalElements;

use DivineaLabs\Swisseph\Data\OrbitalElementsData;
use DivineaLabs\Swisseph\Enums\PlanetBody;

final class OrbitalElementsParser
{
    /**
     * Map each swetest element key (left of the tab) to the captured-values array key.
     *
     * @var array<string, string>
     */
    private const KEY_MAP = [
        'semiaxis' => 'semiAxis',
        'eccentricity' => 'eccentricity',
        'inclination' => 'inclination',
        'asc. node' => 'ascendingNode',
        'arg. pericenter' => 'argPericenter',
        'pericenter' => 'pericenter',
        'mean longitude' => 'meanLongitude',
        'mean anomaly' => 'meanAnomaly',
        'ecc. anomaly' => 'eccentricAnomaly',
        'true anomaly' => 'trueAnomaly',
        'dist. pericenter' => 'distPericenter',
        'dist. apocenter' => 'distApocenter',
        'mean daily motion' => 'meanDailyMotion',
        'sid. period (y)' => 'siderealPeriodYears',
        'trop. period (y)' => 'tropicalPeriodYears',
        'synodic cycle (d)' => 'synodicCycleDays',
    ];

    /**
     * Parse a `-orbel` block (already stripped of the `./` command echo) for one body.
     *
     * Contract: never throws. Unknown keys and header/context lines are skipped.
     *
     * @param  array<int, string>  $lines
     */
    public function parse(array $lines, PlanetBody $body): OrbitalElementsData
    {
        $values = [];
        $timePericenterJd = 0.0;
        $timePericenterCivil = '';

        foreach ($lines as $line) {
            // Split on the first tab: "key\tvalue…".
            $tabPos = strpos((string) $line, "\t");
            if ($tabPos === false) {
                continue; // header/context line — no key\tvalue shape
            }

            $key = trim(substr((string) $line, 0, $tabPos));
            $rest = trim(substr((string) $line, $tabPos + 1));

            if ($rest === '') {
                continue;
            }

            if ($key === 'time pericenter') {
                // "<jd>  <civil date string>" — first whitespace token is the JD float.
                $parts = preg_split('/\s+/', $rest, 2);
                if ($parts === false || $parts === []) {
                    continue;
                }
                $timePericenterJd = (float) $parts[0];
                $timePericenterCivil = isset($parts[1]) ? trim($parts[1]) : '';

                continue;
            }

            $field = self::KEY_MAP[$key] ?? null;
            if ($field === null) {
                continue; // unknown key — tolerant skip
            }

            // Value is the first whitespace-delimited token (defends against trailing junk).
            $token = preg_split('/\s+/', $rest, 2);
            $values[$field] = (float) ($token[0] ?? $rest);
        }

        return new OrbitalElementsData(
            body: $body,
            semiAxis: $values['semiAxis'] ?? 0.0,
            eccentricity: $values['eccentricity'] ?? 0.0,
            inclination: $values['inclination'] ?? 0.0,
            ascendingNode: $values['ascendingNode'] ?? 0.0,
            argPericenter: $values['argPericenter'] ?? 0.0,
            pericenter: $values['pericenter'] ?? 0.0,
            meanLongitude: $values['meanLongitude'] ?? 0.0,
            meanAnomaly: $values['meanAnomaly'] ?? 0.0,
            eccentricAnomaly: $values['eccentricAnomaly'] ?? 0.0,
            trueAnomaly: $values['trueAnomaly'] ?? 0.0,
            timePericenterJd: $timePericenterJd,
            timePericenterCivil: $timePericenterCivil,
            distPericenter: $values['distPericenter'] ?? 0.0,
            distApocenter: $values['distApocenter'] ?? 0.0,
            meanDailyMotion: $values['meanDailyMotion'] ?? 0.0,
            siderealPeriodYears: $values['siderealPeriodYears'] ?? 0.0,
            tropicalPeriodYears: $values['tropicalPeriodYears'] ?? 0.0,
            synodicCycleDays: $values['synodicCycleDays'] ?? 0.0,
        );
    }
}
```

> Note on the civil-string assertion: the reference value is `8.05.2024,  07:45:04.6` with two spaces before the time. `preg_split('/\s+/', $rest, 2)` on `"2460438.822970  8.05.2024,  07:45:04.6"` yields `['2460438.822970', '8.05.2024,  07:45:04.6']` — `$parts[1]` retains the internal double space, matching the fixture assertion exactly.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/OrbitalElements/OrbitalElementsParserTest.php`
Expected: PASS (2 tests, 19 field assertions in the first).

---

### Task 5: Wire the factory, facade, and provider

**Files:**
- Modify: `src/Swisseph.php`
- Modify: `src/Facades/Swisseph.php`
- Modify: `src/SwissephServiceProvider.php`

- [ ] **Step 1: Add the factory method to `Swisseph`**

Add the import and method to the SP0 factory class:

```php
use DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsBuilder;
```

```php
    public function orbitalElements(): OrbitalElementsBuilder
    {
        return app(OrbitalElementsBuilder::class);
    }
```

- [ ] **Step 2: Add the facade `@method` annotation**

In `src/Facades/Swisseph.php`, add to the docblock:

```php
 * @method static \DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsBuilder orbitalElements()
```

- [ ] **Step 3: Register the bindings**

In `SwissephServiceProvider::registeringPackage()` (or wherever SP0 registers the other builders), add:

```php
$this->app->bind(\DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsBuilder::class);
$this->app->bind(\DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsParser::class);
```

- [ ] **Step 4: Write the factory test**

Add `tests/Features/SwissephFactoryTest.php` assertion (or a dedicated file if SP0's already exists — append a test):

```php
use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsBuilder;

it('exposes a fresh orbitalElements() builder', function () {
    expect(Swisseph::orbitalElements())->toBeInstanceOf(OrbitalElementsBuilder::class);
    expect(Swisseph::orbitalElements())->not->toBe(Swisseph::orbitalElements());
});
```

- [ ] **Step 5: Run the wiring + suite**

Run: `vendor/bin/pest tests/Features/Support/OrbitalElements/ tests/Features/SwissephFactoryTest.php`
Expected: PASS.

---

### Task 6: Self-skipping integration test (with the real binary)

**Files:**
- Create: `tests/Features/Support/OrbitalElements/OrbitalElementsIntegrationTest.php`

- [ ] **Step 1: Write the integration test**

Self-skips when the configured `swetest` binary is absent (same pattern as the other integration tests). When the binary exists, it runs the real pipeline for Mars at a fixed date and asserts the elements are within physically-plausible ranges (semi-axis ~1.52 AU, eccentricity ~0.093, sidereal period ~1.88 y) rather than exact strings (values drift slightly across swetest/ephemeris versions).

```php
<?php

use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Facades\Swisseph;

function swetestBinaryAvailable(): bool
{
    $exe = (string) config('swisseph.executable', '');

    return $exe !== '' && is_executable($exe);
}

it('computes Mars orbital elements against the real binary', function () {
    if (! swetestBinaryAvailable()) {
        $this->markTestSkipped('swetest binary not available.');
    }

    $data = Swisseph::orbitalElements()
        ->forBody(PlanetBody::MARS)
        ->from('2026-01-01')
        ->get();

    expect($data->body)->toBe(PlanetBody::MARS);
    expect($data->semiAxis)->toBeGreaterThan(1.4)->toBeLessThan(1.7);
    expect($data->eccentricity)->toBeGreaterThan(0.08)->toBeLessThan(0.11);
    expect($data->siderealPeriodYears)->toBeGreaterThan(1.8)->toBeLessThan(1.95);
    expect($data->timePericenterJd)->toBeGreaterThan(2400000.0);
    expect($data->timePericenterCivil)->not->toBe('');
});
```

- [ ] **Step 2: Run the integration test**

Run: `vendor/bin/pest tests/Features/Support/OrbitalElements/OrbitalElementsIntegrationTest.php`
Expected: PASS if the binary is configured/available; otherwise SKIPPED (1 skipped) — never a failure.

---

### Task 7: Green gate

- [ ] **Step 1: Full suite**

Run: `vendor/bin/pest`
Expected: PASS — all new orbital-element tests green, no regressions.

- [ ] **Step 2: Static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors. If the `$this->body ?? PlanetBody::MARS` line in `get()` is flagged, replace it with `assert($this->body !== null);` after `$command = $this->build();` and pass `$this->body` directly.

---

## Self-Review — every reference key maps to a DTO field

Reference block: `docs/superpowers/specs/swetest-output-reference.md` → "SP7 — Orbital elements". Each `key\tvalue` line below is accounted for. Header/context lines are explicitly skipped.

| Reference line (key) | Action | DTO field | Type |
|---|---|---|---|
| `date (dmy) … TT … version …` | SKIP (no tab-key in map; context) | — | — |
| `UT: … delta t: … sec` | SKIP (no leading tab) | — | — |
| `TT: …` | SKIP (no leading tab) | — | — |
| `Epsilon (t/m) …` | SKIP (no leading tab) | — | — |
| `Nutation …` | SKIP (no leading tab) | — | — |
| `Mars  282°… …` (body position) | SKIP (no leading tab) | — | — |
| `semiaxis` | map | `semiAxis` | float |
| `eccentricity` | map | `eccentricity` | float |
| `inclination` | map | `inclination` | float |
| `asc. node` | map | `ascendingNode` | float |
| `arg. pericenter` | map | `argPericenter` | float |
| `pericenter` | map | `pericenter` | float |
| `mean longitude` | map | `meanLongitude` | float |
| `mean anomaly` | map | `meanAnomaly` | float |
| `ecc. anomaly` | map | `eccentricAnomaly` | float |
| `true anomaly` | map | `trueAnomaly` | float |
| `time pericenter` (JD) | special-case, token 0 | `timePericenterJd` | float |
| `time pericenter` (civil) | special-case, remainder | `timePericenterCivil` | string |
| `dist. pericenter` | map | `distPericenter` | float |
| `dist. apocenter` | map | `distApocenter` | float |
| `mean daily motion` | map | `meanDailyMotion` | float |
| `sid. period (y)` | map | `siderealPeriodYears` | float |
| `trop. period (y)` | map | `tropicalPeriodYears` | float |
| `synodic cycle (d)` | map | `synodicCycleDays` | float |

**Coverage checks:**
- DTO field count: 1 `body` + 18 parsed values = **19** constructor params. All 18 parsed values appear in the table above (16 mapped + 2 from `time pericenter`). ✅
- `body` is supplied by the builder (`forBody`) → passed into `parse()`, not parsed from output. ✅
- Builder contract: `forBody` required (throws `OrbitalElementsBodyNotSetException` at `build()`); `from()` → `-b<d.m.Y>`; always emits `-orbel`; no `-ut` (TT/ET-only); `skipPrefixes: ['./']`. ✅
- Parser tolerance: unknown keys ignored; never throws (guard-and-continue, mirrors `RiseParser`). ✅
- Civil-string exactness: double-space preserved via `preg_split(…, 2)` → `'8.05.2024,  07:45:04.6'`. ✅
- Tests cover: body-required throw, `from()` (string + Carbon), `-orbel` emission, no-`-ut`, full 19-field mapping, unknown-key tolerance, factory freshness, self-skipping integration. ✅
```
