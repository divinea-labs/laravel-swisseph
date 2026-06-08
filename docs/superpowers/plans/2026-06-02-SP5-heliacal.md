# SP5 — Heliacal Events Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `heliacal()` pipeline to the `Swisseph` factory that maps swetest's native `-hev` (heliacal events) function — heliacal rising/setting, morning last, evening first — for a planet (`-p<value>`) or a fixed star (`-pf -xf<name>`), at a required observer location, with optional atmospheric/observer/optical visibility models, returning a typed `HeliacalCollection` of `HeliacalEventData`.

**Architecture:** A new `Support/Heliacal/` unit following the locked SP0 convention. `HeliacalBuilder` `use`s the shared `ResolvesSwissephEnvironment` trait for executable/ephe-dir/eph-options and re-implements the exact `-at/-obs/-opt` model-emission logic from `RisingsBuilder` (atmospheric/observer/optical). It always emits `-hev`, requires a geographic position (throws `HeliacalGeoPositionNotSetException` at `get()` if unset), and supports a planet OR fixed-star target. `HeliacalParser` parses ONE line per event (date `Y/m/d`, time `H:i:s.u`, opt/end times sharing the event's date, duration in minutes), tolerantly skipping malformed lines. Results are `HeliacalEventData` (spatie Data, readonly) collected in `HeliacalCollection`. The `Swisseph` factory gains `heliacal(): HeliacalBuilder`.

**Tech Stack:** PHP 8.3/8.4, Laravel 11/12/13, spatie/laravel-data, spatie/laravel-package-tools, Pest 4, Symfony Process, larastan/PHPStan.

**Conventions inherited from SP0 (MUST follow):**
- Pipeline lives in `src/Support/Heliacal/` with `HeliacalBuilder.php` + `HeliacalParser.php`; DTOs under `src/Data/`; enums under `src/Enums/`; exceptions under `src/Exceptions/`.
- The builder `use`s `ResolvesSwissephEnvironment` and emits a `SwissephCommand` via `build()`.
- `Swisseph::heliacal()` returns a FRESH builder instance resolved from the container.
- The parser receives raw stdout lines already stripped of the command-echo + `geo. long` header lines by `SwissephExecutor` via the builder's declared skip prefixes (`['./', 'geo. long']`).
- Package convention is `Carbon` (UTC), not `CarbonImmutable` (cf. `RiseSetEvent`).

> **Dependency:** This plan assumes SP0 has landed — i.e. `Support/Concerns/ResolvesSwissephEnvironment.php` exists, `Support/Rising/RisingsBuilder.php` exists (formerly `RiseCommandBuilder`), `SwissephExecutor::run(SwissephCommand $command, array $skipPrefixes = [])` accepts skip prefixes, and `Swisseph` is a per-pipeline factory. If executing before SP0 merges, adapt the trait/executor references to the pre-SP0 names (`RiseCommandBuilder`, inline env/eph-option code) accordingly.

---

## File Structure

- Create: `src/Enums/HeliacalEventType.php` — string-backed enum (`HELIACAL_RISING`, `HELIACAL_SETTING`, `MORNING_LAST`, `EVENING_FIRST`) + `fromLabel()`.
- Create: `src/Exceptions/HeliacalGeoPositionNotSetException.php` — thrown at `get()` when no geopos was set.
- Create: `src/Data/HeliacalEventData.php` — readonly spatie Data DTO for one event.
- Create: `src/Data/HeliacalCollection.php` — `all()` / `first()` / `ofType()`.
- Create: `src/Support/Heliacal/HeliacalBuilder.php` — fluent builder, `use ResolvesSwissephEnvironment`.
- Create: `src/Support/Heliacal/HeliacalParser.php` — one-line-per-event parser.
- Modify: `src/Swisseph.php` — add `heliacal(): HeliacalBuilder`.
- Modify: `src/SwissephServiceProvider.php` — bind `HeliacalBuilder`, `HeliacalParser`.
- Modify: `src/Facades/Swisseph.php` — add `@method static … heliacal()`.
- Create tests:
  - `tests/Features/Enums/HeliacalEventTypeTest.php`
  - `tests/Features/Support/Heliacal/HeliacalBuilderTest.php`
  - `tests/Features/Support/Heliacal/HeliacalParserTest.php`
  - `tests/Features/Support/Heliacal/HeliacalIntegrationTest.php` (self-skipping)

---

### Task 1: The HeliacalEventType enum

**Files:**
- Create: `src/Enums/HeliacalEventType.php`
- Test: `tests/Features/Enums/HeliacalEventTypeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Enums\HeliacalEventType;

it('maps each printed swetest label to the right case', function () {
    expect(HeliacalEventType::fromLabel('heliacal rising'))->toBe(HeliacalEventType::HELIACAL_RISING);
    expect(HeliacalEventType::fromLabel('heliacal setting'))->toBe(HeliacalEventType::HELIACAL_SETTING);
    expect(HeliacalEventType::fromLabel('morning last'))->toBe(HeliacalEventType::MORNING_LAST);
    expect(HeliacalEventType::fromLabel('evening first'))->toBe(HeliacalEventType::EVENING_FIRST);
});

it('is whitespace-tolerant and case-insensitive on the label', function () {
    expect(HeliacalEventType::fromLabel('  Heliacal   Rising '))->toBe(HeliacalEventType::HELIACAL_RISING);
    expect(HeliacalEventType::fromLabel('MORNING LAST'))->toBe(HeliacalEventType::MORNING_LAST);
});

it('returns null for an unknown label', function () {
    expect(HeliacalEventType::fromLabel('cosmic burp'))->toBeNull();
});

it('exposes the string backing value', function () {
    expect(HeliacalEventType::HELIACAL_RISING->value)->toBe('heliacal_rising');
    expect(HeliacalEventType::EVENING_FIRST->value)->toBe('evening_first');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Enums/HeliacalEventTypeTest.php`
Expected: FAIL — enum `HeliacalEventType` not found.

- [ ] **Step 3: Write the enum**

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum HeliacalEventType: string
{
    case HELIACAL_RISING = 'heliacal_rising';
    case HELIACAL_SETTING = 'heliacal_setting';
    case MORNING_LAST = 'morning_last';
    case EVENING_FIRST = 'evening_first';

    /**
     * Map the phrase swetest prints (e.g. "heliacal rising", "morning last")
     * to the matching case. Whitespace-collapsed and case-insensitive.
     * Returns null for an unrecognised label so the parser can skip it.
     */
    public static function fromLabel(string $label): ?self
    {
        $normalised = strtolower(trim((string) preg_replace('/\s+/', ' ', $label)));

        return match ($normalised) {
            'heliacal rising' => self::HELIACAL_RISING,
            'heliacal setting' => self::HELIACAL_SETTING,
            'morning last' => self::MORNING_LAST,
            'evening first' => self::EVENING_FIRST,
            default => null,
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Enums/HeliacalEventTypeTest.php`
Expected: PASS (4 tests).

---

### Task 2: The geopos-not-set exception

**Files:**
- Create: `src/Exceptions/HeliacalGeoPositionNotSetException.php`

> No dedicated test file — this exception is asserted in `HeliacalBuilderTest` (Task 5).

- [ ] **Step 1: Write the exception**

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

class HeliacalGeoPositionNotSetException extends \RuntimeException
{
    public static function make(): self
    {
        return new self(
            'A geographic position is required for heliacal events (-hev). '
            .'Call at($longitude, $latitude, $elevation) before get().'
        );
    }
}
```

---

### Task 3: The HeliacalEventData DTO

**Files:**
- Create: `src/Data/HeliacalEventData.php`
- Test: covered by `HeliacalParserTest` (Task 6); a small construction test is included here.

- [ ] **Step 1: Write the failing test**

Append to a new file `tests/Features/Support/Heliacal/HeliacalEventDataTest.php`:

```php
<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\HeliacalEventData;
use DivineaLabs\Swisseph\Enums\HeliacalEventType;

it('holds the event fields as readonly properties', function () {
    $event = new HeliacalEventData(
        body: 'Venus',
        type: HeliacalEventType::HELIACAL_RISING,
        at: Carbon::parse('2026-11-02 04:41:55.9', 'UTC'),
        julianDay: 2461346.69579,
        optimumAt: Carbon::parse('2026-11-02 04:56:15.9', 'UTC'),
        endAt: Carbon::parse('2026-11-02 05:17:59.9', 'UTC'),
        durationMinutes: 36.1,
    );

    expect($event->body)->toBe('Venus');
    expect($event->type)->toBe(HeliacalEventType::HELIACAL_RISING);
    expect($event->at->toDateTimeString())->toBe('2026-11-02 04:41:55');
    expect($event->julianDay)->toBe(2461346.69579);
    expect($event->durationMinutes)->toBe(36.1);
});

it('serialises to an array via spatie Data', function () {
    $event = new HeliacalEventData(
        body: 'Sirius,alCMa',
        type: HeliacalEventType::MORNING_LAST,
        at: Carbon::parse('2027-03-28 03:52:26.5', 'UTC'),
        julianDay: 2461492.66142,
        optimumAt: Carbon::parse('2027-03-28 03:56:28.5', 'UTC'),
        endAt: Carbon::parse('2027-03-28 04:00:08.5', 'UTC'),
        durationMinutes: 7.7,
    );

    $array = $event->toArray();

    expect($array)->toHaveKeys(['body', 'type', 'at', 'julianDay', 'optimumAt', 'endAt', 'durationMinutes']);
    expect($array['body'])->toBe('Sirius,alCMa');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/HeliacalEventDataTest.php`
Expected: FAIL — class `HeliacalEventData` not found.

- [ ] **Step 3: Write the DTO**

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\HeliacalEventType;
use Spatie\LaravelData\Data;

class HeliacalEventData extends Data
{
    public function __construct(
        // Body/star name exactly as printed by swetest (e.g. "Venus", "Sirius,alCMa").
        public readonly string $body,
        public readonly HeliacalEventType $type,
        // Main event time (UTC) — the first time printed on the line.
        public readonly Carbon $at,
        public readonly float $julianDay,
        // "opt" time (UTC) — best observation moment.
        public readonly Carbon $optimumAt,
        // "end" time (UTC) — end of visibility window.
        public readonly Carbon $endAt,
        // Visibility window duration, in minutes (the "dur N.n min" value).
        public readonly float $durationMinutes,
    ) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/HeliacalEventDataTest.php`
Expected: PASS (2 tests).

---

### Task 4: The HeliacalCollection

**Files:**
- Create: `src/Data/HeliacalCollection.php`
- Test: `tests/Features/Support/Heliacal/HeliacalCollectionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\HeliacalCollection;
use DivineaLabs\Swisseph\Data\HeliacalEventData;
use DivineaLabs\Swisseph\Enums\HeliacalEventType;

function makeHeliacalEvent(HeliacalEventType $type, string $body = 'Venus'): HeliacalEventData
{
    return new HeliacalEventData(
        body: $body,
        type: $type,
        at: Carbon::parse('2026-11-02 04:41:55.9', 'UTC'),
        julianDay: 2461346.69579,
        optimumAt: Carbon::parse('2026-11-02 04:56:15.9', 'UTC'),
        endAt: Carbon::parse('2026-11-02 05:17:59.9', 'UTC'),
        durationMinutes: 36.1,
    );
}

it('exposes all events as a plain array', function () {
    $rising = makeHeliacalEvent(HeliacalEventType::HELIACAL_RISING);
    $setting = makeHeliacalEvent(HeliacalEventType::HELIACAL_SETTING);

    $collection = new HeliacalCollection([$rising, $setting]);

    expect($collection->all())->toBe([$rising, $setting]);
});

it('returns the first event, or null when empty', function () {
    $rising = makeHeliacalEvent(HeliacalEventType::HELIACAL_RISING);

    expect((new HeliacalCollection([$rising]))->first())->toBe($rising);
    expect((new HeliacalCollection([]))->first())->toBeNull();
});

it('filters by event type', function () {
    $rising = makeHeliacalEvent(HeliacalEventType::HELIACAL_RISING);
    $morningLast = makeHeliacalEvent(HeliacalEventType::MORNING_LAST);
    $rising2 = makeHeliacalEvent(HeliacalEventType::HELIACAL_RISING);

    $collection = new HeliacalCollection([$rising, $morningLast, $rising2]);

    expect($collection->ofType(HeliacalEventType::HELIACAL_RISING))->toBe([$rising, $rising2]);
    expect($collection->ofType(HeliacalEventType::EVENING_FIRST))->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/HeliacalCollectionTest.php`
Expected: FAIL — class `HeliacalCollection` not found.

- [ ] **Step 3: Write the collection**

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use DivineaLabs\Swisseph\Enums\HeliacalEventType;
use Spatie\LaravelData\Data;

class HeliacalCollection extends Data
{
    public function __construct(
        /** @var array<int, HeliacalEventData> */
        public readonly array $events = [],
    ) {}

    /**
     * @return array<int, HeliacalEventData>
     */
    public function all(): array
    {
        return array_values($this->events);
    }

    public function first(): ?HeliacalEventData
    {
        return $this->events[array_key_first($this->events)] ?? null;
    }

    /**
     * @return array<int, HeliacalEventData>
     */
    public function ofType(HeliacalEventType $type): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (HeliacalEventData $e) => $e->type === $type,
        ));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/HeliacalCollectionTest.php`
Expected: PASS (3 tests).

---

### Task 5: The HeliacalBuilder

**Files:**
- Create: `src/Support/Heliacal/HeliacalBuilder.php`
- Test: `tests/Features/Support/Heliacal/HeliacalBuilderTest.php`

The builder is fluent and immutable-style. Target is either a planet (`-p<value>`) via `forBody()` or a fixed star (`-pf -xf<name>`) via `forStar()` — the last call wins. `at()` is REQUIRED (no swetest default for `-hev` observer position); `build()` throws `HeliacalGeoPositionNotSetException` if it was never called. `from()` maps to `-b<d.m.Y>`, `count()` to `-n<count>` (default 1). The optional `withAtmosphere/withObserver/withOptics` setters re-use the EXACT `-at/-obs/-opt` token formatting from `RisingsBuilder`. `-hev` is always emitted. `get()` runs the command (skipping `['./', 'geo. long']`) and parses to a `HeliacalCollection`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\HeliacalGeoPositionNotSetException;
use DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder;

afterEach(function () {
    Carbon::setTestNow();
});

function buildHeliacal(callable $configure): SwissephCommand
{
    $builder = new HeliacalBuilder;
    $configure($builder);

    return $builder->build();
}

// ---------------------------------------------------------------------------
// -hev always emitted + geopos required
// ---------------------------------------------------------------------------

it('always emits the -hev flag', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0);
    });

    expect($command->arguments)->toContain('hev');
});

it('throws when no geographic position was set', function () {
    $builder = (new HeliacalBuilder)->forBody(PlanetBody::VENUS);

    expect(fn () => $builder->build())
        ->toThrow(HeliacalGeoPositionNotSetException::class);
});

it('emits geopos with longitude, latitude and elevation', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0);
    });

    expect($command->arguments)->toContain('geopos21,52.2,100');
});

it('defaults elevation to zero', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2);
    });

    expect($command->arguments)->toContain('geopos21,52.2,0');
});

// ---------------------------------------------------------------------------
// Target: planet vs fixed star
// ---------------------------------------------------------------------------

it('emits p<value> for a planet target', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0);
    });

    expect($command->arguments)->toContain('p3');
    expect($command->arguments)->not->toContain('pf');
});

it('emits -pf -xf<name> for a fixed-star target', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forStar('Sirius')->at(21.0, 52.2, 100.0);
    });

    expect($command->arguments)->toContain('pf');
    expect($command->arguments)->toContain('xfSirius');
});

it('lets the last target call win (star overrides planet)', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->forStar('Aldebaran')->at(21.0, 52.2, 100.0);
    });

    expect($command->arguments)->toContain('pf');
    expect($command->arguments)->toContain('xfAldebaran');
    expect($command->arguments)->not->toContain('p3');
});

it('lets the last target call win (planet overrides star)', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forStar('Aldebaran')->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0);
    });

    expect($command->arguments)->toContain('p3');
    expect($command->arguments)->not->toContain('pf');
    expect($command->arguments)->not->toContain('xfAldebaran');
});

// ---------------------------------------------------------------------------
// Window: from() + count()
// ---------------------------------------------------------------------------

it('emits b<d.m.Y> from from()', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0)->from('2026-11-01');
    });

    expect($command->arguments)->toContain('b01.11.2026');
});

it('accepts a Carbon instance in from()', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0)
            ->from(Carbon::create(2026, 11, 1, 0, 0, 0, 'UTC'));
    });

    expect($command->arguments)->toContain('b01.11.2026');
});

it('defaults the window start to today UTC when from() is never called', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 2, 12, 0, 0, 'UTC'));

    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0);
    });

    expect($command->arguments)->toContain('b02.06.2026');
});

it('defaults count to 1', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0);
    });

    expect($command->arguments)->toContain('n1');
});

it('emits n<count> from count()', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0)->count(4);
    });

    expect($command->arguments)->toContain('n4');
});

// ---------------------------------------------------------------------------
// Optical / observer / atmospheric models (reuse RisingsBuilder emission)
// ---------------------------------------------------------------------------

it('emits the atmospheric model token', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0)
            ->withAtmosphere(1013.25, 15.0, 40.0, 0.25);
    });

    expect($command->arguments)->toContain('at1013.25,15,40,0.25');
});

it('emits the observer model token', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0)
            ->withObserver(36.0, 1.0);
    });

    expect($command->arguments)->toContain('obs36,1');
});

it('emits the optical model token with the binocular flag', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0)
            ->withOptics(36.0, 1.0, true, 7.0, 50.0, 0.8);
    });

    expect($command->arguments)->toContain('opt36,1,1,7,50,0.8');
});

it('emits binocular flag 0 when false', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0)
            ->withOptics(36.0, 1.0, false, 1.0, 0.0, 0.8);
    });

    expect($command->arguments)->toContain('opt36,1,0,1,0,0.8');
});

it('omits model tokens when their setters were not called', function () {
    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0);
    });

    foreach ($command->arguments as $arg) {
        expect(str_starts_with($arg, 'at'))->toBeFalse();
        expect(str_starts_with($arg, 'obs'))->toBeFalse();
        expect(str_starts_with($arg, 'opt'))->toBeFalse();
    }
});

// ---------------------------------------------------------------------------
// Environment: edir + eph options propagate through the trait
// ---------------------------------------------------------------------------

it('emits the ephemeris dir token and propagated eph options', function () {
    config()->set('swisseph.ephemeris_dir', '/data/ephe');

    $command = buildHeliacal(function (HeliacalBuilder $b) {
        $b->forBody(PlanetBody::VENUS)->at(21.0, 52.2, 100.0)
            ->withEphOptions(EphOptions::SWISS_TYPE);
    });

    expect($command->arguments)->toContain('edir/data/ephe');
    expect($command->arguments)->toContain('eswe');
});
```

> Note on `EphOptions` cases: this plan references `EphOptions::SWISS_TYPE` (emits `eswe`) to mirror the SP0 trait test. If the real enum case name differs, substitute the actual case that emits `eswe` (grep `src/Enums/EphOptions.php`). The token assertion (`'eswe'`) is the load-bearing part.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/HeliacalBuilderTest.php`
Expected: FAIL — class `HeliacalBuilder` not found.

- [ ] **Step 3: Write the builder**

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Heliacal;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\HeliacalCollection;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\HeliacalGeoPositionNotSetException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

final class HeliacalBuilder
{
    use ResolvesSwissephEnvironment;

    /** Skip the command-echo line and the geo header line in stdout. */
    private const SKIP_PREFIXES = ['./', 'geo. long'];

    /** Planet target token value (p<value>); null when a star target is set. */
    private ?int $bodyValue = null;

    /** Fixed-star name (-pf -xf<name>); null when a planet target is set. */
    private ?string $starName = null;

    /** Observer longitude — null until at() is called. */
    private ?float $longitude = null;

    private ?float $latitude = null;

    private float $elevation = 0.0;

    /** Window start; null = default to today UTC at build time. */
    private ?Carbon $windowStart = null;

    private int $count = 1;

    private ?string $atmosphericModel = null;  // "at{press},{temp},{rhum},{visr}"

    private ?string $observerModel = null;     // "obs{age},{sn}"

    private ?string $opticalModel = null;      // "opt{age},{sn},{bin},{m},{d},{t}"

    public function __construct(
        private ?SwissephExecutor $executor = null,
        private ?HeliacalParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
    }

    /**
     * Target a planet (or other -p body). Clears any star target.
     */
    public function forBody(PlanetBody $body): self
    {
        $this->bodyValue = $body->value;
        $this->starName = null;

        return $this;
    }

    /**
     * Target a fixed star by catalog name (-pf -xf<name>). Clears any planet target.
     */
    public function forStar(string $name): self
    {
        $this->starName = $name;
        $this->bodyValue = null;

        return $this;
    }

    /**
     * Observer location. REQUIRED for -hev: build() throws if never called.
     */
    public function at(float $lon, float $lat, float $elev = 0.0): self
    {
        $this->longitude = $lon;
        $this->latitude = $lat;
        $this->elevation = $elev;

        return $this;
    }

    /**
     * Window start date → -b<d.m.Y>. Defaults to today UTC when omitted.
     */
    public function from(Carbon|string $date): self
    {
        $this->windowStart = $date instanceof Carbon
            ? $date->copy()->utc()
            : Carbon::parse($date, 'UTC');

        return $this;
    }

    /**
     * Number of events to compute → -n<count>. Default 1.
     */
    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Emit at{pressure},{temp},{humidity},{visibility}. Mirrors RisingsBuilder::setAtmosphericModel().
     */
    public function withAtmosphere(float $pressure, float $temp, float $humidity, float $visibility): self
    {
        $this->atmosphericModel = 'at'
            .$this->fmt($pressure).','
            .$this->fmt($temp).','
            .$this->fmt($humidity).','
            .$this->fmt($visibility);

        return $this;
    }

    /**
     * Emit obs{age},{sn}. Mirrors RisingsBuilder::setObserverModel().
     */
    public function withObserver(float $age, float $sn): self
    {
        $this->observerModel = 'obs'.$this->fmt($age).','.$this->fmt($sn);

        return $this;
    }

    /**
     * Emit opt{age},{sn},{binocular},{magnification},{diameter},{transmission}.
     * Mirrors RisingsBuilder::setOpticalModel().
     */
    public function withOptics(
        float $age, float $sn, bool $binocular,
        float $magnification, float $diameter, float $transmission
    ): self {
        $this->opticalModel = 'opt'
            .$this->fmt($age).','
            .$this->fmt($sn).','
            .($binocular ? '1' : '0').','
            .$this->fmt($magnification).','
            .$this->fmt($diameter).','
            .$this->fmt($transmission);

        return $this;
    }

    /**
     * Build the swetest command. Throws if no observer position was set.
     *
     * @throws HeliacalGeoPositionNotSetException
     */
    public function build(): SwissephCommand
    {
        if ($this->longitude === null || $this->latitude === null) {
            throw HeliacalGeoPositionNotSetException::make();
        }

        $windowStart = $this->windowStart ?? Carbon::now('UTC')->startOfDay();

        $args = [
            $this->epheDirArg(),
            ...$this->ephOptionArgs(),
        ];

        if ($this->starName !== null) {
            $args[] = 'pf';
            $args[] = 'xf'.$this->starName;
        } else {
            $args[] = 'p'.($this->bodyValue ?? PlanetBody::SUN->value);
        }

        $args[] = 'hev';
        $args[] = 'geopos'.$this->fmt($this->longitude).','.$this->fmt($this->latitude).','.$this->fmt($this->elevation);
        $args[] = 'b'.$windowStart->format('d.m.Y');
        $args[] = 'n'.$this->count;

        if ($this->atmosphericModel !== null) {
            $args[] = $this->atmosphericModel;
        }
        if ($this->observerModel !== null) {
            $args[] = $this->observerModel;
        }
        if ($this->opticalModel !== null) {
            $args[] = $this->opticalModel;
        }

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $args,
        );
    }

    /**
     * Run the command and parse heliacal events.
     */
    public function get(): HeliacalCollection
    {
        $command = $this->build();
        $lines = ($this->executor ?? app(SwissephExecutor::class))->run($command, self::SKIP_PREFIXES);

        return ($this->parser ?? app(HeliacalParser::class))->parse($lines);
    }
}
```

> The trait provides `bootSwissephEnvironment()`, `$executable`, `epheDirArg()`, `ephOptionArgs()`, `withEphOptions()`, and `fmt()`. This builder declares its OWN `$longitude/$latitude/$elevation` (nullable lon/lat so the required-geopos guard works) — it does NOT use the trait's `setLocation`/location state. If PHPStan flags a property redeclaration clash with the trait's `$longitude/$latitude`, exclude those trait properties is not possible; instead name these private fields `$geoLon/$geoLat/$geoElev` and update `at()`/`build()` accordingly. Verify against the actual trait shipped by SP0 and adjust naming so there is no collision (Step 4 PHPStan gate catches this).

- [ ] **Step 4: Run test + static analysis**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/HeliacalBuilderTest.php`
Expected: PASS (all builder tests).

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors. If a trait property collision is reported for `$longitude/$latitude/$elevation`, rename this builder's geo fields to `$geoLon/$geoLat/$geoElev` and re-run.

---

### Task 6: The HeliacalParser

**Files:**
- Create: `src/Support/Heliacal/HeliacalParser.php`
- Test: `tests/Features/Support/Heliacal/HeliacalParserTest.php`

The parser takes raw stdout lines (echo + geo header already stripped by the executor) and produces a `HeliacalCollection`. ONE line per event. The body+event-type words are left-padded before the `:` separator. Split on the FIRST ` : ` (the label is space-padded to align the colon; the spaces vary). Parse the remainder for the main date+time `YYYY/MM/DD HH:MM:SS.s UT (JD)`, then `opt HH:MM:SS.s`, `end HH:MM:SS.s`, `dur N.n min`. The body name is the leading word(s) and the event-type phrase is the trailing word(s); since event-type phrases are a known closed set of two words, take the LAST TWO words of the label as the type and the rest as the body. Tolerant: any line that does not match is skipped.

- [ ] **Step 1: Write the failing test (REAL captured fixture)**

The fixture is the EXACT captured Venus 4-event block from `swetest-output-reference.md` (SP5), already stripped of the `geo. long …` header line that the executor removes.

```php
<?php

use DivineaLabs\Swisseph\Data\HeliacalCollection;
use DivineaLabs\Swisseph\Enums\HeliacalEventType;
use DivineaLabs\Swisseph\Support\Heliacal\HeliacalParser;

/**
 * EXACT captured Venus block (swetest -hev -p3 -geopos21.0,52.2,100 -n4),
 * after the executor stripped the command-echo + "geo. long …" header lines.
 */
function venusHeliacalLines(): array
{
    return [
        'Venus heliacal rising : 2026/11/02 04:41:55.9 UT (2461346.69579), opt 04:56:15.9, end 05:17:59.9, dur 36.1 min',
        'Venus morning last    : 2027/03/28 03:52:26.5 UT (2461492.66142), opt 03:56:28.5, end 04:00:08.5, dur 7.7 min',
        'Venus evening first   : 2027/11/10 15:23:38.9 UT (2461720.14142), opt 15:24:09.9, end 15:24:55.9, dur 1.3 min',
        'Venus heliacal setting: 2028/05/23 18:56:52.6 UT (2461915.28950), opt 19:21:06.6, end 19:38:51.6, dur 42.0 min',
    ];
}

it('parses all four heliacal events', function () {
    $collection = (new HeliacalParser)->parse(venusHeliacalLines());

    expect($collection)->toBeInstanceOf(HeliacalCollection::class);
    expect($collection->all())->toHaveCount(4);
});

it('parses the heliacal rising event field-by-field', function () {
    $event = (new HeliacalParser)->parse(venusHeliacalLines())->all()[0];

    expect($event->body)->toBe('Venus');
    expect($event->type)->toBe(HeliacalEventType::HELIACAL_RISING);
    expect($event->at->format('Y-m-d H:i:s'))->toBe('2026-11-02 04:41:55');
    expect($event->at->micro)->toBe(900000);
    expect($event->julianDay)->toBe(2461346.69579);
    expect($event->optimumAt->format('Y-m-d H:i:s'))->toBe('2026-11-02 04:56:15');
    expect($event->endAt->format('Y-m-d H:i:s'))->toBe('2026-11-02 05:17:59');
    expect($event->durationMinutes)->toBe(36.1);
});

it('parses the morning last event', function () {
    $event = (new HeliacalParser)->parse(venusHeliacalLines())->all()[1];

    expect($event->type)->toBe(HeliacalEventType::MORNING_LAST);
    expect($event->at->format('Y-m-d H:i:s'))->toBe('2027-03-28 03:52:26');
    expect($event->julianDay)->toBe(2461492.66142);
    expect($event->optimumAt->format('Y-m-d H:i:s'))->toBe('2027-03-28 03:56:28');
    expect($event->endAt->format('Y-m-d H:i:s'))->toBe('2027-03-28 04:00:08');
    expect($event->durationMinutes)->toBe(7.7);
});

it('parses the evening first event', function () {
    $event = (new HeliacalParser)->parse(venusHeliacalLines())->all()[2];

    expect($event->type)->toBe(HeliacalEventType::EVENING_FIRST);
    expect($event->at->format('Y-m-d H:i:s'))->toBe('2027-11-10 15:23:38');
    expect($event->julianDay)->toBe(2461720.14142);
    expect($event->optimumAt->format('Y-m-d H:i:s'))->toBe('2027-11-10 15:24:09');
    expect($event->endAt->format('Y-m-d H:i:s'))->toBe('2027-11-10 15:24:55');
    expect($event->durationMinutes)->toBe(1.3);
});

it('parses the heliacal setting event (colon directly after label, no leading space)', function () {
    $event = (new HeliacalParser)->parse(venusHeliacalLines())->all()[3];

    expect($event->type)->toBe(HeliacalEventType::HELIACAL_SETTING);
    expect($event->at->format('Y-m-d H:i:s'))->toBe('2028-05-23 18:56:52');
    expect($event->julianDay)->toBe(2461915.28950);
    expect($event->optimumAt->format('Y-m-d H:i:s'))->toBe('2028-05-23 19:21:06');
    expect($event->endAt->format('Y-m-d H:i:s'))->toBe('2028-05-23 19:38:51');
    expect($event->durationMinutes)->toBe(42.0);
});

it('shares the event date with opt and end times', function () {
    $event = (new HeliacalParser)->parse(venusHeliacalLines())->all()[3];

    // opt/end carry only HH:MM:SS in the output; they inherit the event's date.
    expect($event->optimumAt->format('Y-m-d'))->toBe('2028-05-23');
    expect($event->endAt->format('Y-m-d'))->toBe('2028-05-23');
});

it('keeps the star catalog name as the body when present', function () {
    $lines = [
        'Sirius,alCMa heliacal rising : 2026/11/02 04:41:55.9 UT (2461346.69579), opt 04:56:15.9, end 05:17:59.9, dur 36.1 min',
    ];

    $event = (new HeliacalParser)->parse($lines)->all()[0];

    expect($event->body)->toBe('Sirius,alCMa');
    expect($event->type)->toBe(HeliacalEventType::HELIACAL_RISING);
});

it('returns an empty collection for empty input', function () {
    expect((new HeliacalParser)->parse([])->all())->toBe([]);
});

it('skips malformed or unrelated lines without throwing', function () {
    $lines = [
        'this is not an event line',
        'Venus heliacal rising : 2026/11/02 04:41:55.9 UT (2461346.69579), opt 04:56:15.9, end 05:17:59.9, dur 36.1 min',
        'Venus cosmic burp : 2026/11/02 04:41:55.9 UT (2461346.69579), opt 04:56:15.9, end 05:17:59.9, dur 36.1 min',
        ': 2026/11/02 04:41:55.9 UT (2461346.69579), opt 04:56:15.9, end 05:17:59.9, dur 36.1 min',
    ];

    $collection = (new HeliacalParser)->parse($lines);

    // Only the one well-formed, recognised-type line survives.
    expect($collection->all())->toHaveCount(1);
    expect($collection->all()[0]->type)->toBe(HeliacalEventType::HELIACAL_RISING);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/HeliacalParserTest.php`
Expected: FAIL — class `HeliacalParser` not found.

- [ ] **Step 3: Write the parser**

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Heliacal;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\HeliacalCollection;
use DivineaLabs\Swisseph\Data\HeliacalEventData;
use DivineaLabs\Swisseph\Enums\HeliacalEventType;

final class HeliacalParser
{
    /**
     * Parse swetest -hev output (one line per event) into a HeliacalCollection.
     *
     * Contract: never throws. Any line that does not match the expected shape,
     * or whose event-type phrase is unrecognised, is silently skipped.
     *
     * @param  array<int, string>  $lines
     */
    public function parse(array $lines): HeliacalCollection
    {
        $events = [];

        foreach ($lines as $line) {
            $event = $this->parseLine((string) $line);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return new HeliacalCollection($events);
    }

    private function parseLine(string $line): ?HeliacalEventData
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        // Split label from data on the FIRST ' : ' OR a label immediately
        // followed by ':' (swetest left-pads the label so the colon may have
        // no leading space, e.g. "heliacal setting:").
        $pos = strpos($line, ':');
        if ($pos === false) {
            return null;
        }

        $label = rtrim(substr($line, 0, $pos));
        $rest = trim(substr($line, $pos + 1));

        // Label = "<body words> <event type words>". Event-type phrases are
        // always exactly two words; everything before them is the body name.
        $labelParts = preg_split('/\s+/', trim($label)) ?: [];
        if (count($labelParts) < 3) {
            return null;   // need at least 1 body word + 2 type words
        }

        $typeWords = array_slice($labelParts, -2);
        $bodyWords = array_slice($labelParts, 0, -2);

        $type = HeliacalEventType::fromLabel(implode(' ', $typeWords));
        if ($type === null) {
            return null;
        }

        $body = implode(' ', $bodyWords);
        if ($body === '') {
            return null;
        }

        // Data shape:
        // YYYY/MM/DD HH:MM:SS.s UT (JD), opt HH:MM:SS.s, end HH:MM:SS.s, dur N.n min
        $pattern = '#^'
            .'(\d{4})/(\d{2})/(\d{2})\s+'        // 1 year 2 month 3 day
            .'(\d{2}:\d{2}:\d{2}(?:\.\d+)?)\s+'  // 4 main time
            .'UT\s+\(([\d.]+)\)\s*,\s*'          // 5 julian day
            .'opt\s+(\d{2}:\d{2}:\d{2}(?:\.\d+)?)\s*,\s*'  // 6 opt time
            .'end\s+(\d{2}:\d{2}:\d{2}(?:\.\d+)?)\s*,\s*'  // 7 end time
            .'dur\s+([\d.]+)\s+min'              // 8 duration minutes
            .'#';

        if (! preg_match($pattern, $rest, $m)) {
            return null;
        }

        $date = $m[1].'/'.$m[2].'/'.$m[3];   // Y/m/d

        $at = $this->parseDateTime($date, $m[4]);
        $optimumAt = $this->parseDateTime($date, $m[6]);
        $endAt = $this->parseDateTime($date, $m[7]);

        if ($at === null || $optimumAt === null || $endAt === null) {
            return null;
        }

        return new HeliacalEventData(
            body: $body,
            type: $type,
            at: $at,
            julianDay: (float) $m[5],
            optimumAt: $optimumAt,
            endAt: $endAt,
            durationMinutes: (float) $m[8],
        );
    }

    /**
     * Parse a Y/m/d date and HH:MM:SS[.f] time into a UTC Carbon.
     * Returns null on failure (caller skips the line). Never throws.
     */
    private function parseDateTime(string $date, string $time): ?Carbon
    {
        $parts = explode('.', $time, 2);
        $hms = $parts[0];                 // 'HH:MM:SS'
        $fracRaw = $parts[1] ?? '0';

        // Normalise fraction to 6 digits: '9' → '900000'.
        $micros = (int) str_pad(substr($fracRaw, 0, 6), 6, '0', STR_PAD_RIGHT);

        $dt = Carbon::createFromFormat('Y/m/d H:i:s', "{$date} {$hms}", 'UTC');

        if (! ($dt instanceof Carbon)) {
            return null;
        }

        return $dt->setMicroseconds($micros);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/HeliacalParserTest.php`
Expected: PASS (all parser tests, incl. the 4-event fixture field-by-field).

---

### Task 7: Wire the factory, provider, and facade

**Files:**
- Modify: `src/Swisseph.php`
- Modify: `src/SwissephServiceProvider.php`
- Modify: `src/Facades/Swisseph.php`
- Test: `tests/Features/Support/Heliacal/HeliacalBuilderTest.php` (add a factory assertion)

- [ ] **Step 1: Add a factory test**

Append to `tests/Features/Support/Heliacal/HeliacalBuilderTest.php`:

```php
it('is exposed as a fresh builder via Swisseph::heliacal()', function () {
    $a = \DivineaLabs\Swisseph\Facades\Swisseph::heliacal();
    $b = \DivineaLabs\Swisseph\Facades\Swisseph::heliacal();

    expect($a)->toBeInstanceOf(HeliacalBuilder::class);
    expect($a)->not->toBe($b);   // fresh instance per call
});
```

- [ ] **Step 2: Add `heliacal()` to the Swisseph factory**

In `src/Swisseph.php`, add the import `use DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder;` and the method:

```php
public function heliacal(): HeliacalBuilder
{
    return app(HeliacalBuilder::class);
}
```

- [ ] **Step 3: Bind the new classes in the provider**

In `src/SwissephServiceProvider.php` `registeringPackage()`, add:

```php
$this->app->bind(\DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder::class);
$this->app->bind(\DivineaLabs\Swisseph\Support\Heliacal\HeliacalParser::class);
```

> `HeliacalBuilder`'s constructor params (`?SwissephExecutor`, `?HeliacalParser`) are nullable with defaults, so a plain `bind` (auto-resolution) works; the builder lazy-resolves via `app(...)` in `get()` when null. No closure needed.

- [ ] **Step 4: Add the facade `@method` annotation**

In `src/Facades/Swisseph.php`, add under the existing method block:

```php
 * @method static \DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder heliacal()
```

- [ ] **Step 5: Run the new tests + full suite + static analysis**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/ tests/Features/Enums/HeliacalEventTypeTest.php`
Expected: PASS.

Run: `vendor/bin/pest`
Expected: PASS — whole suite green (no regressions).

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors.

---

### Task 8: Self-skipping integration test (real binary)

**Files:**
- Create: `tests/Features/Support/Heliacal/HeliacalIntegrationTest.php`

Mirrors the existing integration-test pattern: skips automatically when the `swetest` binary is not configured/available, so CI without the binary stays green.

- [ ] **Step 1: Write the integration test**

```php
<?php

use DivineaLabs\Swisseph\Data\HeliacalCollection;
use DivineaLabs\Swisseph\Data\HeliacalEventData;
use DivineaLabs\Swisseph\Enums\HeliacalEventType;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Facades\Swisseph;

beforeEach(function () {
    $exe = (string) config('swisseph.executable', '');
    if ($exe === '' || ! is_file($exe) || ! is_executable($exe)) {
        $this->markTestSkipped('swetest binary not available — integration test skipped.');
    }
});

it('computes Venus heliacal events end-to-end against the real binary', function () {
    $collection = Swisseph::heliacal()
        ->forBody(PlanetBody::VENUS)
        ->at(21.0, 52.2, 100.0)
        ->from('2026-11-01')
        ->count(4)
        ->get();

    expect($collection)->toBeInstanceOf(HeliacalCollection::class);
    expect($collection->all())->not->toBeEmpty();

    $first = $collection->first();
    expect($first)->toBeInstanceOf(HeliacalEventData::class);
    expect($first->body)->toContain('Venus');
    expect($first->type)->toBeInstanceOf(HeliacalEventType::class);
    expect($first->julianDay)->toBeGreaterThan(2400000.0);
    expect($first->durationMinutes)->toBeGreaterThanOrEqual(0.0);
    expect($first->optimumAt->greaterThanOrEqualTo($first->at))->toBeTrue();
    expect($first->endAt->greaterThanOrEqualTo($first->optimumAt))->toBeTrue();
});

it('computes Sirius heliacal events for a fixed-star target', function () {
    $collection = Swisseph::heliacal()
        ->forStar('Sirius')
        ->at(21.0, 52.2, 100.0)
        ->from('2026-01-01')
        ->count(2)
        ->get();

    expect($collection)->toBeInstanceOf(HeliacalCollection::class);
    // Star heliacal phenomena exist for Sirius; assert at least the call round-trips.
    foreach ($collection->all() as $event) {
        expect($event->body)->toContain('Sirius');
    }
});
```

- [ ] **Step 2: Run it (skips without the binary)**

Run: `vendor/bin/pest tests/Features/Support/Heliacal/HeliacalIntegrationTest.php`
Expected: PASS or SKIPPED (skipped when no binary; green when the binary is present and `swisseph.executable` points to it).

---

### Task 9: Final green gate

- [ ] **Step 1: Full suite**

Run: `vendor/bin/pest`
Expected: PASS — all heliacal tests plus the existing suite, no regressions.

- [ ] **Step 2: Static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors.

> Do NOT commit. This plan deliberately omits commit steps — integration/commit is the maintainer's call (public package, review required).

---

## Self-Review notes

- **Spec coverage (SP5):** ✅
  - `HeliacalBuilder` with `forBody()` (`-p<value>`) / `forStar()` (`-pf -xf<name>`), `at()` (`-geopos`, REQUIRED, throws), `from()` (`-b<d.m.Y>`), `count()` (default 1, `-n`), always emits `-hev`, `withAtmosphere/withObserver/withOptics` mirroring `RisingsBuilder` token formats, terminal `get(): HeliacalCollection`, skipPrefixes `['./', 'geo. long']` — Task 5.
  - `HeliacalParser` one-line-per-event, split on the label/colon boundary (handles the left-padded label where the colon may have no leading space, as in `heliacal setting:`), date `Y/m/d` + time `H:i:s.u`, opt/end share the event date, duration minutes, tolerant skip — Task 6.
  - `HeliacalEventData` (spatie Data, readonly): `body`, `type`, `at`, `julianDay`, `optimumAt`, `endAt`, `durationMinutes` — Task 3.
  - `HeliacalCollection` with `all()`/`first()`/`ofType()` — Task 4.
  - `HeliacalEventType` string-backed enum + `fromLabel()` — Task 1.
  - `HeliacalGeoPositionNotSetException` — Task 2.
  - Binding + `Swisseph::heliacal()` + facade `@method` — Task 7.
  - Builder/parser tests incl. the EXACT captured Venus 4-event fixture, geopos-required throw, model emission, from/count, factory; self-skipping integration test — Tasks 5/6/8.
- **Model emission reuse:** `withAtmosphere/withObserver/withOptics` reproduce `RisingsBuilder::setAtmosphericModel/setObserverModel/setOpticalModel` token strings verbatim (`at…`, `obs…`, `opt…` with the `binocular ? '1' : '0'` flag and `fmt()` formatting). ✅
- **Carbon convention:** all times are `Carbon` UTC (not `CarbonImmutable`), matching `RiseSetEvent`. ✅
- **Tolerance:** parser never throws; empty input → empty collection; unknown event-type phrase or malformed data line → skipped (mirrors `RiseParser`). ✅
- **Trait-property collision risk:** flagged in Task 5 Step 3 — if the SP0 trait declares `$longitude/$latitude/$elevation`, rename this builder's geo fields to `$geoLon/$geoLat/$geoElev`; the PHPStan gate (Task 5 Step 4) catches it. The builder intentionally keeps its OWN nullable lon/lat so the required-geopos guard is meaningful (the trait's location state defaults to Greenwich, which would defeat the guard). ✅
- **Fresh-instance factory:** `Swisseph::heliacal()` resolves a new `HeliacalBuilder` per call (no shared mutable state), consistent with SP0's `positions()`/`risings()`. ✅
- **No commits:** plan stops at the green gate; integration is the maintainer's decision. ✅
- **SP0 dependency:** assumes `ResolvesSwissephEnvironment`, `RisingsBuilder`, executor skip-prefix support, and the `Swisseph` factory exist; a fallback note covers pre-SP0 execution. ✅
```