# SP0 — Foundation Restructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the `Swisseph` god-class into a thin factory exposing per-pipeline sub-builders (`positions()`, `risings()`, and a slot for `eclipses()` and later pipelines), backed by a shared environment trait, without changing any astronomical behavior.

**Architecture:** Extract the common config/date/location/eph-option plumbing into a `ResolvesSwissephEnvironment` trait. Rename `SwissephCommandBuilder → PositionsBuilder` and `RiseCommandBuilder → RisingsBuilder` (each gains the trait). `Swisseph` becomes a factory whose methods return a FRESH builder per call (fixing the current shared-mutable-state behavior). The facade and DI provider are updated; the only consumer (`astro-core`, 3 call sites) is migrated.

**Tech Stack:** PHP 8.3/8.4, Laravel 11/12/13, spatie/laravel-data, spatie/laravel-package-tools, Pest 4, Symfony Process, larastan/PHPStan.

**Conventions locked by this plan (all later SP plans MUST follow):**
- Each pipeline lives in `src/Support/<Pipeline>/` with `…Builder.php` (+ `…Parser.php`, DTOs under `src/Data/`, enums under `src/Enums/`).
- Every builder `use`s `ResolvesSwissephEnvironment` for executable/ephe-dir/date/time/location/eph-options and emits a `SwissephCommand` via `build()` (or `buildWithQuery()` where a parse-context object is needed).
- `Swisseph::<pipeline>()` returns a fresh builder instance resolved from the container.
- Parsers receive raw stdout lines already stripped of the command-echo + `geo. long` header by `SwissephExecutor` when the builder declares header lines to skip.

---

## File Structure

- Create: `src/Support/Concerns/ResolvesSwissephEnvironment.php` — shared env/date/location/eph-option state + arg builders.
- Move/Rename: `src/Support/Command/SwissephCommandBuilder.php` → `src/Support/Positions/PositionsBuilder.php` (class `PositionsBuilder`), refactored to use the trait.
- Move/Rename: `src/Support/Parsing/SwissephParser.php` → `src/Support/Positions/PositionsParser.php` (class `PositionsParser`).
- Move/Rename: `src/Support/Rising/RiseCommandBuilder.php` → `src/Support/Rising/RisingsBuilder.php` (class `RisingsBuilder`), refactored to use the trait.
- Modify: `src/Support/Command/SwissephExecutor.php` — add optional skip-prefix filtering.
- Rewrite: `src/Swisseph.php` — thin factory: `positions(): PositionsBuilder`, `risings(): RisingsBuilder`, terminal helpers moved onto the builders.
- Modify: `src/Facades/Swisseph.php` — new `@method` annotations.
- Modify: `src/SwissephServiceProvider.php` — bind renamed classes; factory closure.
- Modify (astro-core): `src/Domains/Events/Providers/SwissephEphemerisProvider.php`, `src/Domains/PlanetaryTime/Providers/SwissEphSolarTimesProvider.php`, `src/Domains/Returns/Support/ReturnChartBuilder.php`.
- Modify tests: rename `tests/Features/Support/Command/SwissephCommandBuilderTest.php` → `…/Positions/PositionsBuilderTest.php`, etc.
- Modify: `README.md`, `CHANGELOG.md`.

---

### Task 1: Extract the shared environment trait

**Files:**
- Create: `src/Support/Concerns/ResolvesSwissephEnvironment.php`
- Test: `tests/Features/Support/Concerns/ResolvesSwissephEnvironmentTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

it('resolves executable and normalized ephemeris dir from config', function () {
    config()->set('swisseph.executable', '/bin/swetest');
    config()->set('swisseph.ephemeris_dir', 'C:\\ephe\\');

    $host = new class {
        use ResolvesSwissephEnvironment;
        public function __construct() { $this->bootSwissephEnvironment(); }
        public function exe(): string { return $this->executable; }
        public function edir(): string { return $this->epheDirArg(); }
    };

    expect($host->exe())->toBe('/bin/swetest');
    expect($host->edir())->toBe('edir/C:/ephe');
});

it('builds date and ut time arguments in swetest format', function () {
    $host = new class {
        use ResolvesSwissephEnvironment;
        public function __construct() { $this->bootSwissephEnvironment(); }
        public function d(): string { return $this->dateArg(); }
        public function t(): string { return $this->utTimeArg(); }
    };
    $host->setDateTime('2026-01-01 12:30:00', 'UTC');

    expect($host->d())->toBe('b01.01.2026');
    expect($host->t())->toBe('ut12:30:00');
});

it('de-dupes and sorts eph options', function () {
    $host = new class {
        use ResolvesSwissephEnvironment;
        public function __construct() { $this->bootSwissephEnvironment(); }
        public function opts(): array { return $this->ephOptionArgs(); }
    };
    $host->withEphOptions(EphOptions::TRUE_POSITIONS, EphOptions::SWISS_TYPE, EphOptions::SWISS_TYPE);

    expect($host->opts())->toBe(['eswe', 'true']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Concerns/ResolvesSwissephEnvironmentTest.php`
Expected: FAIL — trait `ResolvesSwissephEnvironment` not found.

- [ ] **Step 3: Write the trait**

Extract the env/date/location/eph-option logic verbatim from the current `SwissephCommandBuilder` constructor + `setDateTime`/`setLocation`/`setPlace`/`withEphOptions`/`fmt`/`buildDateArgument`/`buildTimeArgument`/`buildEphOptions`.

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Concerns;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Exceptions\InvalidEphOptionPassedException;

trait ResolvesSwissephEnvironment
{
    protected string $executable = '';

    protected string $epheDir = '';

    protected Carbon $dateTime;

    /** @var array<string, EphOptions> */
    protected array $ephOptions = [];

    // NOTE: location ($longitude/$latitude/$elevation/$place) is intentionally NOT in this
    // trait. Only the position/rising pipelines have a single geographic context; event
    // builders (eclipses/occultations/heliacal/meridian) carry their OWN nullable geo
    // fields so they can enforce "geopos required". Keeping location out of the trait
    // avoids fatal property-redeclaration clashes in those builders.

    protected function bootSwissephEnvironment(): void
    {
        $this->executable = (string) config('swisseph.executable', '');

        $dir = str_replace('\\', '/', (string) config('swisseph.ephemeris_dir', ''));
        $dir = rtrim($dir, '/');
        $this->epheDir = '/'.ltrim($dir, '/');

        foreach ((array) config('swisseph.eph_options', []) as $v) {
            if ($v instanceof EphOptions) {
                $this->ephOptions[$v->value] = $v;
            }
        }

        $this->dateTime = Carbon::now()->utc();
    }

    public function setDateTime(Carbon|string $date, string $tz = 'UTC'): static
    {
        $dt = is_string($date) ? Carbon::parse($date, $tz) : $date;
        $this->dateTime = $dt->utc();

        return $this;
    }

    public function withEphOptions(EphOptions|array ...$options): static
    {
        foreach ($options as $opt) {
            $items = is_array($opt) ? $opt : [$opt];
            foreach ($items as $item) {
                if (! $item instanceof EphOptions) {
                    throw new InvalidEphOptionPassedException($item);
                }
                $this->ephOptions[$item->value] = $item;
            }
        }

        return $this;
    }

    protected function epheDirArg(): string
    {
        return 'edir'.$this->epheDir;
    }

    protected function dateArg(): string
    {
        return 'b'.$this->dateTime->format('d.m.Y');
    }

    protected function utTimeArg(): string
    {
        return 'ut'.$this->dateTime->format('H:i:s');
    }

    /** @return string[] */
    protected function ephOptionArgs(): array
    {
        $opts = array_values($this->ephOptions);
        usort($opts, static fn (EphOptions $a, EphOptions $b) => $a->value <=> $b->value);

        return array_map(static fn (EphOptions $o) => $o->value, $opts);
    }

    protected function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 8, '.', ''), '0'), '.');
    }

    public function getDate(): Carbon { return $this->dateTime; }
}
```

> **Normalization note (added in review):** location is deliberately excluded from the trait (see the inline NOTE above). `PositionsBuilder` and `RisingsBuilder` KEEP their own `$longitude/$latitude/$elevation/$place` fields + `setLocation()/setPlace()/getLongitude()/getLatitude()/getPlace()`. Event builders (SP1/SP2/SP5/SP6) declare their own nullable geo fields (e.g. `?float $geoLon`) and a `local()`/`at()` setter. The factory test in Task 5 asserts `getLongitude()` on `PositionsBuilder`, which therefore still exists.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Concerns/ResolvesSwissephEnvironmentTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Support/Concerns/ResolvesSwissephEnvironment.php tests/Features/Support/Concerns/ResolvesSwissephEnvironmentTest.php
git commit -m "feat(core): extract ResolvesSwissephEnvironment trait"
```

---

### Task 2: Add optional header-skip filtering to the executor

**Files:**
- Modify: `src/Support/Command/SwissephExecutor.php`
- Test: `tests/Features/Support/Command/SwissephExecutorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;

it('strips the command-echo and geo header lines when skip prefixes are given', function () {
    // Use `printf` as a fake binary that emits a swetest-like block.
    $command = new SwissephCommand(
        executable: 'printf',
        arguments: [], // not used by toProcessArray below
    );

    // Build a command that prints an echo line, a geo header, and a data line.
    $cmd = new SwissephCommand(
        executable: '/bin/sh',
        arguments: [],
    );

    $executor = new SwissephExecutor();

    $lines = $executor->runRaw(
        ['/bin/sh', '-c', "printf '%s\\n' './swetests -edir./ephe' 'geo. long 21.0, lat 52.2, alt 100.0' 'partial 12.08.2026'"],
        skipPrefixes: ['./swetests', 'geo. long'],
    );

    expect($lines)->toBe(['partial 12.08.2026']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Command/SwissephExecutorTest.php`
Expected: FAIL — `runRaw()` not defined.

- [ ] **Step 3: Implement the filtering**

Refactor `SwissephExecutor` so `run()` stays backward-compatible and a new `runRaw()` accepts an explicit process array + skip prefixes. `run()` delegates with no skip prefixes.

```php
<?php

namespace DivineaLabs\Swisseph\Support\Command;

use DivineaLabs\Swisseph\Data\SwissephCommand;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SwissephExecutor
{
    /**
     * @param  string[]  $skipPrefixes  Lines starting with any of these (after trim) are dropped.
     * @return array<int, string>
     */
    public function run(SwissephCommand $command, array $skipPrefixes = []): array
    {
        // swetest echoes the invoked command line (starting with the executable path)
        // as the first stdout line in special-event modes. Auto-skip it so callers only
        // need to declare mode-specific headers (e.g. 'geo. long'). The executable is an
        // ABSOLUTE path in production, so matching on it is robust (a literal './' is not).
        return $this->runRaw(
            $command->toProcessArray(),
            array_merge([$command->executable], $skipPrefixes),
        );
    }

    /**
     * @param  string[]  $processArray
     * @param  string[]  $skipPrefixes
     * @return array<int, string>
     */
    public function runRaw(array $processArray, array $skipPrefixes = []): array
    {
        $process = new Process($processArray);
        $process->setTimeout((float) config('swisseph.timeout', 10));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [];
        }

        $lines = preg_split("/\R/u", $output) ?: [];
        $lines = array_map('trim', $lines);

        return array_values(array_filter(
            $lines,
            static function (string $line) use ($skipPrefixes): bool {
                if ($line === '') {
                    return false;
                }
                foreach ($skipPrefixes as $prefix) {
                    if (str_starts_with($line, $prefix)) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Command/SwissephExecutorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/Command/SwissephExecutor.php tests/Features/Support/Command/SwissephExecutorTest.php
git commit -m "feat(core): executor can skip command-echo and header lines"
```

---

### Task 3: Rename SwissephCommandBuilder → PositionsBuilder (use the trait)

**Files:**
- Move: `src/Support/Command/SwissephCommandBuilder.php` → `src/Support/Positions/PositionsBuilder.php`
- Move: `src/Support/Parsing/SwissephParser.php` → `src/Support/Positions/PositionsParser.php`
- Move test: `tests/Features/Support/Command/SwissephCommandBuilderTest.php` → `tests/Features/Support/Positions/PositionsBuilderTest.php`
- Move test: `tests/Features/Support/Parsing/SwissephParserTest.php` → `tests/Features/Support/Positions/PositionsParserTest.php`

- [ ] **Step 1: Move files with git**

```bash
mkdir -p src/Support/Positions tests/Features/Support/Positions
git mv src/Support/Command/SwissephCommandBuilder.php src/Support/Positions/PositionsBuilder.php
git mv src/Support/Parsing/SwissephParser.php src/Support/Positions/PositionsParser.php
git mv tests/Features/Support/Command/SwissephCommandBuilderTest.php tests/Features/Support/Positions/PositionsBuilderTest.php
git mv tests/Features/Support/Parsing/SwissephParserTest.php tests/Features/Support/Positions/PositionsParserTest.php
```

- [ ] **Step 2: Rename classes + namespaces + adopt the trait**

In `src/Support/Positions/PositionsBuilder.php`:
- `namespace DivineaLabs\Swisseph\Support\Positions;`
- `class PositionsBuilder` (was `SwissephCommandBuilder`).
- Add `use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;` and `use ResolvesSwissephEnvironment;` inside the class.
- DELETE only the members the trait now provides: the `$options`, `$ephOptions`, `$dateTime` properties; the `setDateTime`/`withEphOptions` methods; `fmt`/`buildDateArgument`/`buildTimeArgument`/`buildEphOptions`; and the `getDate` getter.
- KEEP (location is NOT in the trait): `$longitude/$latitude/$elevation/$place` properties; `setLocation`/`setPlace` methods; `getPlace/getLatitude/getLongitude` getters. Also keep position-specific members (`$bodies`, `$defaultProperties`, `$customProperties`, `$observerPosition`, `$planetocentricPlanet`, `$siderealOption`, `$houseSystem`).
- Constructor becomes just: `public function __construct() { $this->bootSwissephEnvironment(); $this->defaultProperties = [ … ]; }` (keep the default-properties init; drop the env init now in the trait).
- In `build()`, replace `$this->options['ephe_dir']` with `$this->epheDirArg()`, `$this->options['executable']` with `$this->executable`, and the date/time arg calls already map to `dateArg()`/`utTimeArg()`.

In `src/Support/Positions/PositionsParser.php`:
- `namespace DivineaLabs\Swisseph\Support\Positions;`
- `class PositionsParser` (was `SwissephParser`).
- Update the `parse()` signature type-hint from `SwissephCommandBuilder` to `PositionsBuilder`.

- [ ] **Step 3: Update test namespaces/use-statements + the renamed-class references**

In both moved test files, replace `SwissephCommandBuilder` → `PositionsBuilder`, `SwissephParser` → `PositionsParser`, and their `use` imports to the new namespace `DivineaLabs\Swisseph\Support\Positions\…`. No assertion changes — generated args and parsing behavior are identical.

- [ ] **Step 4: Update references elsewhere**

Run a repo grep and fix imports in `src/Swisseph.php` and `src/SwissephServiceProvider.php` (done fully in Tasks 5–6, but update the `use` lines now so the suite can load):

Run: `grep -rln "SwissephCommandBuilder\|Support\\\\Command\\\\SwissephCommandBuilder\|Support\\\\Parsing\\\\SwissephParser" src tests`
Fix each hit to the new class/namespace.

- [ ] **Step 5: Run the position tests**

Run: `vendor/bin/pest tests/Features/Support/Positions/`
Expected: PASS (same count as before the rename — the existing builder/parser assertions are unchanged).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(core): rename SwissephCommandBuilder→PositionsBuilder, SwissephParser→PositionsParser, adopt env trait"
```

---

### Task 4: Rename RiseCommandBuilder → RisingsBuilder (use the trait)

**Files:**
- Move: `src/Support/Rising/RiseCommandBuilder.php` → `src/Support/Rising/RisingsBuilder.php`
- Move test: `tests/Features/Support/Rising/RiseCommandBuilderTest.php` → `tests/Features/Support/Rising/RisingsBuilderTest.php`

- [ ] **Step 1: Move files with git**

```bash
git mv src/Support/Rising/RiseCommandBuilder.php src/Support/Rising/RisingsBuilder.php
git mv tests/Features/Support/Rising/RiseCommandBuilderTest.php tests/Features/Support/Rising/RisingsBuilderTest.php
```

- [ ] **Step 2: Rename class + adopt trait**

In `src/Support/Rising/RisingsBuilder.php`:
- `class RisingsBuilder` (was `RiseCommandBuilder`).
- Add `use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;` + `use ResolvesSwissephEnvironment;`.
- This builder's `setDateTime` has a DIFFERENT signature than the trait (Mode A/B timezone handling, `$timezone` nullable). The trait no longer carries `setLocation` (location was removed from the trait in review), so ONLY `setDateTime` clashes. Resolve it by aliasing the trait's version out of the way and keeping the builder's own public `setDateTime`:

```php
use ResolvesSwissephEnvironment {
    setDateTime as private bootSetDateTime;
}
```

Then keep the builder's existing public `setDateTime(Carbon|string $date, ?string $timezone)` and its own `setLocation` as-is. Use the trait only for `executable`, `epheDir`, `epheDirArg()`, `ephOptionArgs()`, `fmt()`, `withEphOptions()`. Delete the builder's now-duplicated `fmt()`/eph-option/executable/epheDir code (the trait provides it).

- [ ] **Step 3: Update test class references**

In the moved test, replace `RiseCommandBuilder` → `RisingsBuilder` and the `use` import. No assertion changes.

- [ ] **Step 4: Run the rising tests**

Run: `vendor/bin/pest tests/Features/Support/Rising/`
Expected: PASS (same count as before).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor(core): rename RiseCommandBuilder→RisingsBuilder, adopt env trait"
```

---

### Task 5: Turn Swisseph into a sub-builder factory

**Files:**
- Rewrite: `src/Swisseph.php`
- Test: `tests/Features/SwissephFactoryTest.php`

The current terminal methods move ONTO the builders so each pipeline is self-contained:
- `PositionsBuilder` gains `get(): AstroTimeFrame` and `getCliCommand(): string` (inject executor + parser).
- `RisingsBuilder` gains `getRiseSetEvents()`, `getRiseSetEventsForBodies()`, `getSunEvents()`, `setRiseBody()` (inject executor + parser).

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;
use DivineaLabs\Swisseph\Support\Rising\RisingsBuilder;

it('exposes fresh sub-builders per pipeline', function () {
    expect(Swisseph::positions())->toBeInstanceOf(PositionsBuilder::class);
    expect(Swisseph::risings())->toBeInstanceOf(RisingsBuilder::class);
});

it('returns an isolated builder on each positions() call (no shared mutable state)', function () {
    $a = Swisseph::positions()->setLocation(10.0, 20.0);
    $b = Swisseph::positions();

    expect($b->getLongitude())->not->toBe(10.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/SwissephFactoryTest.php`
Expected: FAIL — `positions()` not defined.

- [ ] **Step 3: Move terminal methods onto PositionsBuilder**

Add to `PositionsBuilder` constructor params (resolved via container): `protected ?SwissephExecutor $executor = null, protected ?PositionsParser $parser = null` and lazy-resolve with `app(...)` if null. Add:

```php
public function get(): \DivineaLabs\Swisseph\Data\AstroTimeFrame
{
    $command = $this->build();
    $lines = ($this->executor ?? app(SwissephExecutor::class))->run($command);

    return ($this->parser ?? app(PositionsParser::class))->parse($lines, $this);
}

public function getCliCommand(): string
{
    return $this->build()->toCliString();
}
```

- [ ] **Step 4: Move terminal methods onto RisingsBuilder**

Move `getRiseSetEvents()`, `getRiseSetEventsForBodies()`, `getSunEvents()`, and the `$_riseBody` field + `setRiseBody()` verbatim from the old `Swisseph` class into `RisingsBuilder`, injecting `SwissephExecutor` + `RiseParser`. Replace `$this->riseCommandBuilder->buildWithQuery(...)` with `$this->buildWithQuery(...)` (the builder is now `$this`).

- [ ] **Step 5: Rewrite Swisseph as a factory**

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph;

use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;
use DivineaLabs\Swisseph\Support\Rising\RisingsBuilder;

class Swisseph
{
    public function positions(): PositionsBuilder
    {
        return app(PositionsBuilder::class);
    }

    public function risings(): RisingsBuilder
    {
        return app(RisingsBuilder::class);
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/SwissephFactoryTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(core): Swisseph becomes a per-pipeline sub-builder factory"
```

---

### Task 6: Update DI provider + facade

**Files:**
- Modify: `src/SwissephServiceProvider.php`
- Modify: `src/Facades/Swisseph.php`

- [ ] **Step 1: Update the provider bindings**

```php
public function registeringPackage()
{
    $this->app->bind(\DivineaLabs\Swisseph\Support\Command\SwissephExecutor::class);
    $this->app->bind(\DivineaLabs\Swisseph\Support\Positions\PositionsBuilder::class);
    $this->app->bind(\DivineaLabs\Swisseph\Support\Positions\PositionsParser::class);
    $this->app->bind(\DivineaLabs\Swisseph\Support\Rising\RisingsBuilder::class);
    $this->app->bind(\DivineaLabs\Swisseph\Support\Rising\RiseParser::class);

    $this->app->bind(Swisseph::class, fn () => new Swisseph());
    $this->app->alias(Swisseph::class, 'swisseph');
}
```

- [ ] **Step 2: Replace the facade `@method` annotations**

Set the docblock to the new surface:

```php
/**
 * @mixin \DivineaLabs\Swisseph\Swisseph
 *
 * @method static \DivineaLabs\Swisseph\Support\Positions\PositionsBuilder positions()
 * @method static \DivineaLabs\Swisseph\Support\Rising\RisingsBuilder risings()
 */
```

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS — all migrated tests green.

- [ ] **Step 4: Run static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors. Fix any stale type references the move surfaced.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor(core): wire provider + facade to sub-builder factory"
```

---

### Task 7: Migrate the astro-core consumer

**Files (astro-core repo `/home/user/www/divinea/astro-core`):**
- Modify: `src/Domains/Events/Providers/SwissephEphemerisProvider.php`
- Modify: `src/Domains/PlanetaryTime/Providers/SwissEphSolarTimesProvider.php`
- Modify: `src/Domains/Returns/Support/ReturnChartBuilder.php`

> First create a feature branch in astro-core: `git checkout -b feature/swisseph-subbuilders`.

- [ ] **Step 1: Update the ephemeris provider**

In `SwissephEphemerisProvider.php`, change `Swisseph::setLocation(...)` to `Swisseph::positions()->setLocation(...)`. The rest of the chain (`->setDateTime()->withProperties()->withHouses()->get()`) is unchanged because those methods now live on `PositionsBuilder`.

- [ ] **Step 2: Update the solar-times provider**

In `SwissEphSolarTimesProvider.php`, change any `Swisseph::…->getRiseSetEvents()/getSunEvents()` chain to start with `Swisseph::risings()->…`. Map `setLocation/setDateTime` onto the risings builder.

- [ ] **Step 3: Update the return-chart builder**

In `ReturnChartBuilder.php`, change `Swisseph::setLocation(...)` (position chart) to `Swisseph::positions()->setLocation(...)`.

- [ ] **Step 4: Run astro-core tests**

Run: `cd /home/user/www/divinea/astro-core && vendor/bin/pest`
Expected: PASS (unit tests use FakeEphemerisProvider; integration tests self-skip without the binary). Fix any call-site signature mismatch.

- [ ] **Step 5: Commit (astro-core, local only — do NOT push)**

```bash
cd /home/user/www/divinea/astro-core
git add -A
git commit -m "refactor: adopt laravel-swisseph sub-builder API (positions()/risings())"
```

---

### Task 8: Docs — README + CHANGELOG

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Update README usage**

Replace flat-API examples (`Swisseph::setLocation(...)->…->get()`) with the sub-builder entry points: `Swisseph::positions()->setLocation(...)->…->get()` and `Swisseph::risings()->…->getSunEvents()`. Add a short "Pipelines" section listing `positions()` / `risings()` (and note `eclipses()` etc. are coming in SP1+).

- [ ] **Step 2: Add a CHANGELOG entry**

Under a new `## [Unreleased]` / `0.3.0` heading: "BREAKING: entry points restructured into sub-builders — use `Swisseph::positions()` / `Swisseph::risings()` instead of the flat fluent API. Internal: shared `ResolvesSwissephEnvironment` trait; executor header-skip support."

- [ ] **Step 3: Commit**

```bash
cd /home/user/www/divinea/laravel-swisseph
git add README.md CHANGELOG.md
git commit -m "docs: document sub-builder restructure (0.3.0, breaking)"
```

---

## Self-Review notes

- **Spec coverage:** SP0 §3.1–§3.6 covered — trait (T1), executor enhancement (T2), renames (T3/T4), factory (T5), provider/facade (T6), astro-core migration (T7), docs (T8). ✅
- **Type consistency:** `PositionsBuilder`/`PositionsParser`/`RisingsBuilder` names used consistently; `get()/getCliCommand()` on PositionsBuilder; rise terminals on RisingsBuilder. ✅
- **Trait conflict:** RisingsBuilder's divergent `setDateTime/setLocation` handled via `insteadof/as` alias (T4 Step 2). ✅
- **No push:** all commits local; astro-core gets its own local branch (T7). ✅
