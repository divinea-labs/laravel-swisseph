# SP2 — Occultations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a native `Swisseph::occultations()` pipeline that wraps swetest's `-occult` mode — occultation of a fixed star or planet by the Moon — returning a typed `OccultationCollection` of `OccultationEventData`, for both global and local (observer-relative) scopes.

**Architecture:** Mirror the SP1 eclipses pipeline shape (locked by SP0). A fluent `OccultationsBuilder` (`use ResolvesSwissephEnvironment`) emits a `SwissephCommand`; a block-aware `OccultationParser` maps swetest's multi-line records onto readonly spatie/laravel-data DTOs. The builder always emits `-occult`, requires a target (star via `-pf -xf<name>` or planet via `-p<value>`), defaults to `global()`, and exposes `local()`, `from()`, `count()`, `backward()`. The executor strips the command-echo and `geo. long` header lines via `skipPrefixes`. `Swisseph::occultations()` returns a fresh builder resolved from the container; the facade gains a matching `@method`.

**Tech Stack:** PHP 8.3/8.4, Laravel 11/12/13, spatie/laravel-data, spatie/laravel-package-tools, Pest 4, Symfony Process, larastan/PHPStan.

**Conventions inherited from SP0 (followed verbatim):**
- Pipeline lives in `src/Support/Occultations/` with `OccultationsBuilder.php` + `OccultationParser.php`; DTOs under `src/Data/`; enums under `src/Enums/`; exception under `src/Exceptions/`.
- The builder `use`s `ResolvesSwissephEnvironment` (executable/ephe-dir/eph-options/`fmt()`) and emits a `SwissephCommand` via `build()`.
- `Swisseph::occultations()` returns a fresh builder instance resolved from the container.
- The parser receives stdout lines already stripped of the command-echo + `geo. long` header by `SwissephExecutor` because the builder declares `skipPrefixes(['./', 'geo. long'])`.

**Ground-truth output (from `docs/superpowers/specs/swetest-output-reference.md` → "SP2 — Occultations"):**

Global (`-occult -pf -xfAldebaran -n1`) — 3 lines/event:
```
total   non-central 18.08.2033	  12:51:36.0	-3476.300002 km	1.000000	2463828.035833
	  12:06:05.0    12:06:05.0    13:36:53.3    13:36:53.3 dt=73.2
	 107° 7'	  72°35'
```
- L1: `<type>` (may include `non-central`/`central`) · date(d.m.Y) · maxTime(UT) · coreShadowKm · magnitude · JD
- L2: 4 contacts · `dt=<deltaT>`
- L3: lon · lat

Local (`-occult … -local -geopos…`) — 2 lines/event after geo header:
```
geo. long 21.000000, lat 52.200000, alt 100.000000
total            29.01.2034	  16:04:52.8	1.000000	2463992.170056
	52 min 21.03 sec	  15:39:02.2    15:39:02.2    16:31:23.2    16:31:23.2  dt=73.3
```
- L1: `<type>` · date · maxTime(UT) · magnitude · JD  (NO coreShadowKm)
- L2: duration · 4 local contacts · `dt=<deltaT>`

**There is NO Saros field in occultation output** (unlike eclipses). Tokenize on `\s+`; `-` is a null placeholder.

---

## File Structure

- Create: `src/Enums/OccultationType.php` — string-backed `TOTAL`/`ANNULAR`/`PARTIAL`.
- Create: `src/Enums/Centrality.php` — string-backed `CENTRAL`/`NON_CENTRAL`.
- Create: `src/Enums/OccultationScope.php` — string-backed `GLOBAL`/`LOCAL` (kept self-contained, NOT reusing `EclipseScope`).
- Create: `src/Data/OccultationContacts.php` — value object: `exteriorStart`, `interiorStart`, `interiorEnd`, `exteriorEnd` (each `?Carbon`).
- Create: `src/Data/OccultationLocation.php` — value object: `longitude`, `latitude` (`float`).
- Create: `src/Data/OccultationEventData.php` — final readonly DTO.
- Create: `src/Data/OccultationCollection.php` — wrapper with `all()`, `first()`.
- Create: `src/Exceptions/OccultationTargetNotSetException.php`.
- Create: `src/Support/Occultations/OccultationsBuilder.php` — fluent builder.
- Create: `src/Support/Occultations/OccultationParser.php` — block-aware parser.
- Modify: `src/Swisseph.php` — add `occultations(): OccultationsBuilder`.
- Modify: `src/Facades/Swisseph.php` — add `@method` annotation.
- Modify: `src/SwissephServiceProvider.php` — bind the new builder + parser.
- Create test: `tests/Features/Support/Occultations/OccultationsBuilderTest.php`.
- Create test: `tests/Features/Support/Occultations/OccultationParserTest.php`.
- Create test: `tests/Features/Support/Occultations/OccultationsIntegrationTest.php` (self-skipping).

> **Dependency:** This plan assumes SP0 is merged — `ResolvesSwissephEnvironment` trait exists, `SwissephExecutor::run(SwissephCommand, array $skipPrefixes)` supports skip-prefix filtering, and `Swisseph` is a sub-builder factory. If SP0 is not yet in place, execute it first.

---

### Task 1: Enums — OccultationType, Centrality, OccultationScope

**Files:**
- Create: `src/Enums/OccultationType.php`
- Create: `src/Enums/Centrality.php`
- Create: `src/Enums/OccultationScope.php`
- Test: `tests/Features/Support/Occultations/OccultationsEnumsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;

it('backs occultation types with swetest type words', function () {
    expect(OccultationType::TOTAL->value)->toBe('total');
    expect(OccultationType::ANNULAR->value)->toBe('annular');
    expect(OccultationType::PARTIAL->value)->toBe('partial');
    expect(OccultationType::from('total'))->toBe(OccultationType::TOTAL);
});

it('backs centrality with swetest qualifier words', function () {
    expect(Centrality::CENTRAL->value)->toBe('central');
    expect(Centrality::NON_CENTRAL->value)->toBe('non-central');
    expect(Centrality::from('non-central'))->toBe(Centrality::NON_CENTRAL);
});

it('backs scope with global/local', function () {
    expect(OccultationScope::GLOBAL->value)->toBe('global');
    expect(OccultationScope::LOCAL->value)->toBe('local');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationsEnumsTest.php`
Expected: FAIL — enums `OccultationType`/`Centrality`/`OccultationScope` not found.

- [ ] **Step 3: Write the enums**

`src/Enums/OccultationType.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum OccultationType: string
{
    case TOTAL = 'total';
    case ANNULAR = 'annular';
    case PARTIAL = 'partial';
}
```

`src/Enums/Centrality.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum Centrality: string
{
    case CENTRAL = 'central';
    case NON_CENTRAL = 'non-central';
}
```

`src/Enums/OccultationScope.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum OccultationScope: string
{
    case GLOBAL = 'global';
    case LOCAL = 'local';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationsEnumsTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Enums/OccultationType.php src/Enums/Centrality.php src/Enums/OccultationScope.php tests/Features/Support/Occultations/OccultationsEnumsTest.php
git commit -m "feat(occultations): add OccultationType, Centrality, OccultationScope enums"
```

---

### Task 2: Value objects — OccultationContacts, OccultationLocation

**Files:**
- Create: `src/Data/OccultationContacts.php`
- Create: `src/Data/OccultationLocation.php`
- Test: `tests/Features/Support/Occultations/OccultationValueObjectsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationContacts;
use DivineaLabs\Swisseph\Data\OccultationLocation;

it('holds the four captured contacts with nullables', function () {
    $start = Carbon::parse('2033-08-18 12:06:05', 'UTC');
    $end = Carbon::parse('2033-08-18 13:36:53', 'UTC');

    $contacts = new OccultationContacts(
        exteriorStart: $start,
        interiorStart: $start,
        interiorEnd: $end,
        exteriorEnd: $end,
    );

    expect($contacts->exteriorStart)->toEqual($start);
    expect($contacts->interiorStart)->toEqual($start);
    expect($contacts->interiorEnd)->toEqual($end);
    expect($contacts->exteriorEnd)->toEqual($end);
});

it('allows null contacts', function () {
    $contacts = new OccultationContacts(null, null, null, null);

    expect($contacts->exteriorStart)->toBeNull();
    expect($contacts->exteriorEnd)->toBeNull();
});

it('holds a decimal-degree location', function () {
    $loc = new OccultationLocation(longitude: 107.116667, latitude: 72.583333);

    expect($loc->longitude)->toBe(107.116667);
    expect($loc->latitude)->toBe(72.583333);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationValueObjectsTest.php`
Expected: FAIL — classes `OccultationContacts`/`OccultationLocation` not found.

- [ ] **Step 3: Write the value objects**

`src/Data/OccultationContacts.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

/**
 * The four contact instants of an occultation (Moon limb vs. occulted body).
 *
 * Global: exterior/interior ingress + interior/exterior egress.
 * Local: the same four columns, with `-` placeholders mapped to null.
 */
class OccultationContacts extends Data
{
    public function __construct(
        public readonly ?Carbon $exteriorStart,
        public readonly ?Carbon $interiorStart,
        public readonly ?Carbon $interiorEnd,
        public readonly ?Carbon $exteriorEnd,
    ) {}
}
```

`src/Data/OccultationLocation.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

/**
 * The sub-point (longitude/latitude, decimal degrees) of a global occultation.
 */
class OccultationLocation extends Data
{
    public function __construct(
        public readonly float $longitude,
        public readonly float $latitude,
    ) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationValueObjectsTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Data/OccultationContacts.php src/Data/OccultationLocation.php tests/Features/Support/Occultations/OccultationValueObjectsTest.php
git commit -m "feat(occultations): add OccultationContacts + OccultationLocation value objects"
```

---

### Task 3: DTO — OccultationEventData

**Files:**
- Create: `src/Data/OccultationEventData.php`
- Test: `tests/Features/Support/Occultations/OccultationEventDataTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationContacts;
use DivineaLabs\Swisseph\Data\OccultationEventData;
use DivineaLabs\Swisseph\Data\OccultationLocation;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;

function globalEvent(): OccultationEventData
{
    $start = Carbon::parse('2033-08-18 12:06:05', 'UTC');
    $end = Carbon::parse('2033-08-18 13:36:53.3', 'UTC');

    return new OccultationEventData(
        type: OccultationType::TOTAL,
        centrality: Centrality::NON_CENTRAL,
        scope: OccultationScope::GLOBAL,
        maxAt: Carbon::parse('2033-08-18 12:51:36', 'UTC'),
        julianDay: 2463828.035833,
        deltaT: 73.2,
        magnitude: 1.0,
        coreShadowKm: -3476.300002,
        contacts: new OccultationContacts($start, $start, $end, $end),
        location: new OccultationLocation(107.116667, 72.583333),
        duration: null,
    );
}

it('exposes scope and type accessors', function () {
    $event = globalEvent();

    expect($event->isLocal())->toBeFalse();
    expect($event->isTotal())->toBeTrue();
    expect($event->scope)->toBe(OccultationScope::GLOBAL);
    expect($event->centrality)->toBe(Centrality::NON_CENTRAL);
    expect($event->coreShadowKm)->toBe(-3476.300002);
    expect($event->location)->toBeInstanceOf(OccultationLocation::class);
    expect($event->duration)->toBeNull();
});

it('reports local scope and null centrality for a local event', function () {
    $start = Carbon::parse('2034-01-29 15:39:02.2', 'UTC');
    $end = Carbon::parse('2034-01-29 16:31:23.2', 'UTC');

    $event = new OccultationEventData(
        type: OccultationType::TOTAL,
        centrality: null,
        scope: OccultationScope::LOCAL,
        maxAt: Carbon::parse('2034-01-29 16:04:52.8', 'UTC'),
        julianDay: 2463992.170056,
        deltaT: 73.3,
        magnitude: 1.0,
        coreShadowKm: null,
        contacts: new OccultationContacts($start, $start, $end, $end),
        location: null,
        duration: 3141.03,
    );

    expect($event->isLocal())->toBeTrue();
    expect($event->isTotal())->toBeTrue();
    expect($event->centrality)->toBeNull();
    expect($event->coreShadowKm)->toBeNull();
    expect($event->location)->toBeNull();
    expect($event->duration)->toBe(3141.03);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationEventDataTest.php`
Expected: FAIL — class `OccultationEventData` not found.

- [ ] **Step 3: Write the DTO**

`src/Data/OccultationEventData.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;
use Spatie\LaravelData\Data;

/**
 * One occultation event (a star/planet occulted by the Moon).
 *
 * `centrality`, `coreShadowKm` and `location` are global-only (null for local).
 * `duration` is local-only (seconds; null for global).
 */
class OccultationEventData extends Data
{
    public function __construct(
        public readonly OccultationType $type,
        public readonly ?Centrality $centrality,
        public readonly OccultationScope $scope,
        public readonly Carbon $maxAt,
        public readonly float $julianDay,
        public readonly float $deltaT,
        public readonly float $magnitude,
        public readonly ?float $coreShadowKm,
        public readonly OccultationContacts $contacts,
        public readonly ?OccultationLocation $location,
        public readonly ?float $duration,
    ) {}

    public function isLocal(): bool
    {
        return $this->scope === OccultationScope::LOCAL;
    }

    public function isTotal(): bool
    {
        return $this->type === OccultationType::TOTAL;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationEventDataTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Data/OccultationEventData.php tests/Features/Support/Occultations/OccultationEventDataTest.php
git commit -m "feat(occultations): add OccultationEventData DTO with scope/type accessors"
```

---

### Task 4: Collection wrapper — OccultationCollection

**Files:**
- Create: `src/Data/OccultationCollection.php`
- Test: `tests/Features/Support/Occultations/OccultationCollectionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Data\OccultationContacts;
use DivineaLabs\Swisseph\Data\OccultationEventData;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;

function sampleEvent(): OccultationEventData
{
    return new OccultationEventData(
        type: OccultationType::TOTAL,
        centrality: Centrality::CENTRAL,
        scope: OccultationScope::GLOBAL,
        maxAt: Carbon::parse('2033-08-18 12:51:36', 'UTC'),
        julianDay: 2463828.035833,
        deltaT: 73.2,
        magnitude: 1.0,
        coreShadowKm: -3476.3,
        contacts: new OccultationContacts(null, null, null, null),
        location: null,
        duration: null,
    );
}

it('exposes all() and first()', function () {
    $a = sampleEvent();
    $b = sampleEvent();

    $collection = new OccultationCollection([$a, $b]);

    expect($collection->all())->toBe([$a, $b]);
    expect($collection->first())->toBe($a);
});

it('returns null first() when empty', function () {
    $collection = new OccultationCollection([]);

    expect($collection->all())->toBe([]);
    expect($collection->first())->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationCollectionTest.php`
Expected: FAIL — class `OccultationCollection` not found.

- [ ] **Step 3: Write the collection**

`src/Data/OccultationCollection.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class OccultationCollection extends Data
{
    /**
     * @param  array<int, OccultationEventData>  $events
     */
    public function __construct(
        public readonly array $events,
    ) {}

    /**
     * @return array<int, OccultationEventData>
     */
    public function all(): array
    {
        return array_values($this->events);
    }

    public function first(): ?OccultationEventData
    {
        return $this->all()[0] ?? null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationCollectionTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Data/OccultationCollection.php tests/Features/Support/Occultations/OccultationCollectionTest.php
git commit -m "feat(occultations): add OccultationCollection wrapper"
```

---

### Task 5: Exception — OccultationTargetNotSetException

**Files:**
- Create: `src/Exceptions/OccultationTargetNotSetException.php`
- Test: covered indirectly by the builder test (Task 6); no standalone test.

- [ ] **Step 1: Write the exception**

`src/Exceptions/OccultationTargetNotSetException.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

class OccultationTargetNotSetException extends \RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No occultation target set. Call forStar() or forBody() before get().'
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Exceptions/OccultationTargetNotSetException.php
git commit -m "feat(occultations): add OccultationTargetNotSetException"
```

---

### Task 6: Builder — OccultationsBuilder

**Files:**
- Create: `src/Support/Occultations/OccultationsBuilder.php`
- Test: `tests/Features/Support/Occultations/OccultationsBuilderTest.php`

The builder is fluent and immutable-style: target (`forStar`/`forBody`), scope (`global`/`local`), window (`from`/`count`/`backward`). It always emits `-occult`. It `use`s `ResolvesSwissephEnvironment` for `executable`, `epheDirArg()`, `ephOptionArgs()`, and `fmt()`. The terminal `get()` lazy-resolves the executor + parser via `app()` and passes `skipPrefixes(['./', 'geo. long'])` to the executor.

> **Argument-order note:** `build()` emits `executable` implicitly via `SwissephCommand::executable`; arguments are emitted WITHOUT leading dashes (SwissephCommand prepends `-`). So `-occult` is the argument string `occult`, `-pf` is `pf`, `-xfAldebaran` is `xfAldebaran`, etc.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\OccultationTargetNotSetException;
use DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder;

beforeEach(function () {
    config()->set('swisseph.executable', '/bin/swetest');
    config()->set('swisseph.ephemeris_dir', '/ephe');
    config()->set('swisseph.eph_options', []);
});

function buildArgs(OccultationsBuilder $builder): array
{
    return $builder->build()->arguments;
}

it('throws when no target is set', function () {
    $builder = new OccultationsBuilder();

    $builder->build();
})->throws(OccultationTargetNotSetException::class);

it('emits a star target via -pf -xf<name> and always -occult, global by default', function () {
    $builder = (new OccultationsBuilder())->forStar('Aldebaran');

    $args = buildArgs($builder);

    expect($args)->toContain('occult');
    expect($args)->toContain('pf');
    expect($args)->toContain('xfAldebaran');
    expect($args)->toContain('n1');               // default count
    expect($args)->not->toContain('local');       // global default
});

it('emits a planet target via -p<value>', function () {
    $builder = (new OccultationsBuilder())->forBody(PlanetBody::JUPITER);

    $args = buildArgs($builder);

    expect($args)->toContain('occult');
    expect($args)->toContain('p5');                // Jupiter = 5
    expect($args)->not->toContain('pf');
});

it('emits -local -geopos<lon>,<lat>,<elev> for local scope with float params', function () {
    $builder = (new OccultationsBuilder())
        ->forStar('Aldebaran')
        ->local(21.0, 52.2, 100.0);

    $args = buildArgs($builder);

    expect($args)->toContain('local');
    expect($args)->toContain('geopos21,52.2,100');
});

it('defaults elevation to 0 in local scope', function () {
    $builder = (new OccultationsBuilder())
        ->forStar('Aldebaran')
        ->local(21.0, 52.2);

    expect(buildArgs($builder))->toContain('geopos21,52.2,0');
});

it('emits -b<d.m.Y> from from()', function () {
    $builder = (new OccultationsBuilder())
        ->forStar('Aldebaran')
        ->from('2033-08-01');

    expect(buildArgs($builder))->toContain('b01.08.2033');
});

it('accepts a Carbon for from()', function () {
    $builder = (new OccultationsBuilder())
        ->forStar('Aldebaran')
        ->from(\Carbon\Carbon::parse('2033-08-01', 'UTC'));

    expect(buildArgs($builder))->toContain('b01.08.2033');
});

it('emits -n<count> from count()', function () {
    $builder = (new OccultationsBuilder())
        ->forStar('Aldebaran')
        ->count(5);

    expect(buildArgs($builder))->toContain('n5');
});

it('emits -bwd from backward()', function () {
    $builder = (new OccultationsBuilder())
        ->forStar('Aldebaran')
        ->backward();

    expect(buildArgs($builder))->toContain('bwd');
});

it('does not emit -bwd by default', function () {
    $builder = (new OccultationsBuilder())->forStar('Aldebaran');

    expect(buildArgs($builder))->not->toContain('bwd');
});

it('emits the executable and ephemeris dir from config', function () {
    $builder = (new OccultationsBuilder())->forStar('Aldebaran');
    $command = $builder->build();

    expect($command->executable)->toBe('/bin/swetest');
    expect($command->arguments)->toContain('edir/ephe');
});

it('propagates eph options through the shared trait', function () {
    $builder = (new OccultationsBuilder())
        ->forStar('Aldebaran')
        ->withEphOptions(EphOptions::TRUE_POSITIONS);

    expect(buildArgs($builder))->toContain('true');
});

it('combines star target, local scope, from, count and backward', function () {
    $builder = (new OccultationsBuilder())
        ->forStar('Aldebaran')
        ->local(21.0, 52.2, 100.0)
        ->from('2034-01-01')
        ->count(3)
        ->backward();

    $args = buildArgs($builder);

    expect($args)->toContain('occult');
    expect($args)->toContain('pf');
    expect($args)->toContain('xfAldebaran');
    expect($args)->toContain('local');
    expect($args)->toContain('geopos21,52.2,100');
    expect($args)->toContain('b01.01.2034');
    expect($args)->toContain('n3');
    expect($args)->toContain('bwd');
});
```

> Replace `EphOptions::TRUE_POSITIONS` with whatever case is present in the package's `EphOptions` enum if the name differs (it emits the `true` arg in SP0's reference). Confirm the case name in `src/Enums/EphOptions.php` before running.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationsBuilderTest.php`
Expected: FAIL — class `OccultationsBuilder` not found.

- [ ] **Step 3: Write the builder**

`src/Support/Occultations/OccultationsBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Occultations;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\OccultationTargetNotSetException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

final class OccultationsBuilder
{
    use ResolvesSwissephEnvironment;

    /** Lines the executor must strip before the parser sees them. */
    private const SKIP_PREFIXES = ['./', 'geo. long'];

    /** Star target — emitted as `-pf -xf<name>`. Mutually exclusive with $body. */
    private ?string $star = null;

    /** Planet target — emitted as `-p<value>`. Mutually exclusive with $star. */
    private ?PlanetBody $body = null;

    private OccultationScope $scope = OccultationScope::GLOBAL;

    private float $longitude = 0.0;

    private float $latitude = 0.0;

    private float $elevation = 0.0;

    private ?Carbon $from = null;

    private int $count = 1;

    private bool $backward = false;

    public function __construct(
        private ?SwissephExecutor $executor = null,
        private ?OccultationParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
    }

    public function forStar(string $name): self
    {
        $this->star = $name;
        $this->body = null;

        return $this;
    }

    public function forBody(PlanetBody $body): self
    {
        $this->body = $body;
        $this->star = null;

        return $this;
    }

    public function global(): self
    {
        $this->scope = OccultationScope::GLOBAL;

        return $this;
    }

    public function local(float $lon, float $lat, float $elev = 0.0): self
    {
        $this->scope = OccultationScope::LOCAL;
        $this->longitude = $lon;
        $this->latitude = $lat;
        $this->elevation = $elev;

        return $this;
    }

    public function from(Carbon|string $date): self
    {
        $this->from = $date instanceof Carbon
            ? $date->copy()->utc()
            : Carbon::parse($date, 'UTC');

        return $this;
    }

    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function backward(): self
    {
        $this->backward = true;

        return $this;
    }

    public function build(): SwissephCommand
    {
        if ($this->star === null && $this->body === null) {
            throw OccultationTargetNotSetException::make();
        }

        $args = [
            $this->epheDirArg(),
            'occult',
        ];

        // Target
        if ($this->star !== null) {
            $args[] = 'pf';
            $args[] = 'xf'.$this->star;
        } else {
            /** @var PlanetBody $body */
            $body = $this->body;
            $args[] = 'p'.$body->value;
        }

        // Scope
        if ($this->scope === OccultationScope::LOCAL) {
            $args[] = 'local';
            $args[] = 'geopos'.$this->fmt($this->longitude)
                .','.$this->fmt($this->latitude)
                .','.$this->fmt($this->elevation);
        }

        // Window
        if ($this->from !== null) {
            $args[] = 'b'.$this->from->format('d.m.Y');
        }

        $args[] = 'n'.$this->count;

        if ($this->backward) {
            $args[] = 'bwd';
        }

        foreach ($this->ephOptionArgs() as $opt) {
            $args[] = $opt;
        }

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $args,
        );
    }

    public function get(): OccultationCollection
    {
        $command = $this->build();

        $lines = ($this->executor ?? app(SwissephExecutor::class))
            ->run($command, self::SKIP_PREFIXES);

        return ($this->parser ?? app(OccultationParser::class))
            ->parse($lines, $this->scope);
    }

    public function getCliCommand(): string
    {
        return $this->build()->toCliString();
    }
}
```

> **`fmt()` note:** the trait's `fmt()` trims trailing zeros, so `21.0` → `21`, `52.2` → `52.2`, `100.0` → `100`. The builder test asserts `geopos21,52.2,100` and `geopos21,52.2,0` accordingly. This matches the package's location-formatting convention.

> **Property clash note:** `ResolvesSwissephEnvironment` already declares `$longitude`/`$latitude`/`$elevation` (Greenwich defaults). To avoid surprises, this builder re-declares them as `private` with `0.0` defaults and uses `local()` to set them — re-declaration with the SAME visibility-or-narrower is required. If PHP raises a redeclaration error because the trait declares them `protected`, REMOVE the three local re-declarations and rely on the trait's properties, but reset them to `0.0` inside the constructor right after `bootSwissephEnvironment()` so the default-Greenwich values don't leak into `geopos`. Verify which path compiles before proceeding; keep the builder test assertions (`geopos21,52.2,100`) as the source of truth.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationsBuilderTest.php`
Expected: PASS (all builder cases — star/planet target × global/local × backward × from/count × missing-target throws × eph-option propagation).

- [ ] **Step 5: Commit**

```bash
git add src/Support/Occultations/OccultationsBuilder.php tests/Features/Support/Occultations/OccultationsBuilderTest.php
git commit -m "feat(occultations): add fluent OccultationsBuilder"
```

---

### Task 7: Parser — OccultationParser

**Files:**
- Create: `src/Support/Occultations/OccultationParser.php`
- Test: `tests/Features/Support/Occultations/OccultationParserTest.php`

The parser is block-aware and tolerant (skip malformed records, never throw — mirrors `RiseParser`). It branches on the passed `OccultationScope`:
- **Global:** 3 lines/event. L1 = `<type>` [+ `central`/`non-central` qualifier] · date · maxTime · coreShadowKm (with `km` token) · magnitude · JD. L2 = 4 contacts · `dt=<deltaT>`. L3 = lon · lat.
- **Local:** 2 lines/event. L1 = `<type>` · date · maxTime · magnitude · JD (no coreShadowKm). L2 = duration (`N min S.s sec`) · 4 contacts · `dt=<deltaT>`.

Tokenize on `\s+`. `-` → null contact. Times share `RiseParser`'s `d.m.Y` + `H:i:s.u` parsing.

- [ ] **Step 1: Write the failing test (fixtures = the EXACT captured Aldebaran blocks)**

```php
<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;
use DivineaLabs\Swisseph\Support\Occultations\OccultationParser;

/**
 * Lines as the executor hands them to the parser: command-echo and the
 * `geo. long` header are already stripped, each line trimmed.
 */
function globalLines(): array
{
    return [
        "total   non-central 18.08.2033\t  12:51:36.0\t-3476.300002 km\t1.000000\t2463828.035833",
        "12:06:05.0    12:06:05.0    13:36:53.3    13:36:53.3 dt=73.2",
        "107° 7'\t  72°35'",
    ];
}

function localLines(): array
{
    return [
        "total            29.01.2034\t  16:04:52.8\t1.000000\t2463992.170056",
        "52 min 21.03 sec\t  15:39:02.2    15:39:02.2    16:31:23.2    16:31:23.2  dt=73.3",
    ];
}

it('parses a global occultation block field-by-field', function () {
    $parser = new OccultationParser();

    $collection = $parser->parse(globalLines(), OccultationScope::GLOBAL);

    expect($collection)->toBeInstanceOf(OccultationCollection::class);
    expect($collection->all())->toHaveCount(1);

    $event = $collection->first();

    expect($event->type)->toBe(OccultationType::TOTAL);
    expect($event->centrality)->toBe(Centrality::NON_CENTRAL);
    expect($event->scope)->toBe(OccultationScope::GLOBAL);
    expect($event->maxAt->format('Y-m-d H:i:s'))->toBe('2033-08-18 12:51:36');
    expect($event->julianDay)->toBe(2463828.035833);
    expect($event->deltaT)->toBe(73.2);
    expect($event->magnitude)->toBe(1.0);
    expect($event->coreShadowKm)->toBe(-3476.300002);

    expect($event->contacts->exteriorStart->format('H:i:s'))->toBe('12:06:05');
    expect($event->contacts->interiorStart->format('H:i:s'))->toBe('12:06:05');
    expect($event->contacts->interiorEnd->format('H:i:s'))->toBe('13:36:53');
    expect($event->contacts->exteriorEnd->format('H:i:s'))->toBe('13:36:53');

    expect($event->location)->not->toBeNull();
    expect(round($event->location->longitude, 4))->toBe(round(107 + 7 / 60, 4));
    expect(round($event->location->latitude, 4))->toBe(round(72 + 35 / 60, 4));

    expect($event->duration)->toBeNull();
});

it('parses a local occultation block field-by-field', function () {
    $parser = new OccultationParser();

    $collection = $parser->parse(localLines(), OccultationScope::LOCAL);

    expect($collection->all())->toHaveCount(1);

    $event = $collection->first();

    expect($event->type)->toBe(OccultationType::TOTAL);
    expect($event->centrality)->toBeNull();
    expect($event->scope)->toBe(OccultationScope::LOCAL);
    expect($event->maxAt->format('Y-m-d H:i:s'))->toBe('2034-01-29 16:04:52');
    expect($event->julianDay)->toBe(2463992.170056);
    expect($event->deltaT)->toBe(73.3);
    expect($event->magnitude)->toBe(1.0);
    expect($event->coreShadowKm)->toBeNull();

    // duration: 52 min 21.03 sec = 3141.03 s
    expect($event->duration)->toBe(3141.03);

    expect($event->contacts->exteriorStart->format('H:i:s'))->toBe('15:39:02');
    expect($event->contacts->exteriorEnd->format('H:i:s'))->toBe('16:31:23');

    expect($event->location)->toBeNull();
});

it('maps a `-` contact placeholder to null', function () {
    $parser = new OccultationParser();

    $lines = [
        "total            29.01.2034\t  16:04:52.8\t1.000000\t2463992.170056",
        "52 min 21.03 sec\t  15:39:02.2     -            16:31:23.2    16:31:23.2  dt=73.3",
    ];

    $event = $parser->parse($lines, OccultationScope::LOCAL)->first();

    expect($event->contacts->exteriorStart->format('H:i:s'))->toBe('15:39:02');
    expect($event->contacts->interiorStart)->toBeNull();
    expect($event->contacts->interiorEnd->format('H:i:s'))->toBe('16:31:23');
});

it('returns an empty collection for no input', function () {
    $parser = new OccultationParser();

    expect($parser->parse([], OccultationScope::GLOBAL)->all())->toBe([]);
});

it('skips a malformed record without throwing', function () {
    $parser = new OccultationParser();

    $lines = [
        'garbage line that is not a type word',
        'another junk line',
    ];

    expect($parser->parse($lines, OccultationScope::GLOBAL)->all())->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationParserTest.php`
Expected: FAIL — class `OccultationParser` not found.

- [ ] **Step 3: Write the parser**

`src/Support/Occultations/OccultationParser.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Occultations;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Data\OccultationContacts;
use DivineaLabs\Swisseph\Data\OccultationEventData;
use DivineaLabs\Swisseph\Data\OccultationLocation;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;

final class OccultationParser
{
    private const TYPE_WORDS = ['total', 'annular', 'partial'];

    /**
     * Parse swetest -occult output into a collection.
     *
     * Contract: never throws. Malformed records are skipped.
     *
     * @param  array<int, string>  $lines  already stripped of echo + geo header
     */
    public function parse(array $lines, OccultationScope $scope): OccultationCollection
    {
        $blockSize = $scope === OccultationScope::GLOBAL ? 3 : 2;

        // Group lines into records, each starting at a known type word.
        $events = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $first = $this->firstToken($lines[$i]);

            if (! in_array($first, self::TYPE_WORDS, true)) {
                continue; // not the start of a record — tolerant skip
            }

            if ($i + $blockSize - 1 >= $count) {
                break; // incomplete trailing record
            }

            $block = array_slice($lines, $i, $blockSize);

            $event = $scope === OccultationScope::GLOBAL
                ? $this->parseGlobal($block)
                : $this->parseLocal($block);

            if ($event !== null) {
                $events[] = $event;
                $i += $blockSize - 1; // advance past the consumed block
            }
        }

        return new OccultationCollection($events);
    }

    /**
     * Global record: 3 lines.
     * L1: <type> [central|non-central] date maxTime <km> km magnitude JD
     * L2: c1 c2 c3 c4 dt=<deltaT>
     * L3: lon lat
     *
     * @param  array<int, string>  $block
     */
    private function parseGlobal(array $block): ?OccultationEventData
    {
        $l1 = $this->tokens($block[0]);
        $l2 = $this->tokens($block[1]);
        $l3 = $this->tokens($block[2]);

        $type = $this->parseType($l1[0] ?? null);
        if ($type === null) {
            return null;
        }

        // Optional centrality qualifier shifts the remaining columns.
        $offset = 1;
        $centrality = $this->parseCentrality($l1[1] ?? null);
        if ($centrality !== null) {
            $offset = 2;
        }

        $date = $l1[$offset] ?? null;
        $time = $l1[$offset + 1] ?? null;
        $coreShadowKm = $this->toFloat($l1[$offset + 2] ?? null); // "km" word skipped below
        // tokens: [date, time, coreShadow, 'km', magnitude, JD]
        $magnitude = $this->toFloat($l1[$offset + 4] ?? null);
        $julianDay = $this->toFloat($l1[$offset + 5] ?? null);

        $maxAt = $this->parseDateTime($date, $time);
        if ($maxAt === null || $magnitude === null || $julianDay === null) {
            return null;
        }

        $contacts = $this->parseContacts($l2, $date);
        $deltaT = $this->parseDeltaT($l2);

        $location = $this->parseLocation($l3);

        return new OccultationEventData(
            type: $type,
            centrality: $centrality,
            scope: OccultationScope::GLOBAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT ?? 0.0,
            magnitude: $magnitude,
            coreShadowKm: $coreShadowKm,
            contacts: $contacts,
            location: $location,
            duration: null,
        );
    }

    /**
     * Local record: 2 lines.
     * L1: <type> date maxTime magnitude JD
     * L2: <N> min <S> sec c1 c2 c3 c4 dt=<deltaT>
     *
     * @param  array<int, string>  $block
     */
    private function parseLocal(array $block): ?OccultationEventData
    {
        $l1 = $this->tokens($block[0]);
        $l2 = $this->tokens($block[1]);

        $type = $this->parseType($l1[0] ?? null);
        if ($type === null) {
            return null;
        }

        $date = $l1[1] ?? null;
        $time = $l1[2] ?? null;
        $magnitude = $this->toFloat($l1[3] ?? null);
        $julianDay = $this->toFloat($l1[4] ?? null);

        $maxAt = $this->parseDateTime($date, $time);
        if ($maxAt === null || $magnitude === null || $julianDay === null) {
            return null;
        }

        // L2: "N min S sec" then 4 contacts then dt=
        $duration = $this->parseDuration($l2);

        // Drop the leading duration tokens ("N", "min", "S", "sec") so the
        // contact columns start at index 0.
        $contactTokens = $this->stripDurationTokens($l2);
        $contacts = $this->parseContacts($contactTokens, $date);
        $deltaT = $this->parseDeltaT($l2);

        return new OccultationEventData(
            type: $type,
            centrality: null,
            scope: OccultationScope::LOCAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT ?? 0.0,
            magnitude: $magnitude,
            coreShadowKm: null,
            contacts: $contacts,
            location: null,
            duration: $duration,
        );
    }

    /**
     * Build OccultationContacts from the first four contact tokens of a line.
     * `-` placeholders become null.
     *
     * @param  array<int, string>  $tokens  starting at the first contact column
     */
    private function parseContacts(array $tokens, ?string $date): OccultationContacts
    {
        $cols = [];
        foreach ($tokens as $tok) {
            if (count($cols) === 4) {
                break;
            }
            if (str_starts_with($tok, 'dt=')) {
                break;
            }
            if ($tok === '-') {
                $cols[] = null;

                continue;
            }
            if (! $this->isTime($tok)) {
                continue;
            }
            $cols[] = $this->parseDateTime($date, $tok);
        }

        // Pad to four slots.
        $cols = array_pad($cols, 4, null);

        return new OccultationContacts(
            exteriorStart: $cols[0],
            interiorStart: $cols[1],
            interiorEnd: $cols[2],
            exteriorEnd: $cols[3],
        );
    }

    private function parseLocation(array $tokens): ?OccultationLocation
    {
        $lon = $this->parseAngleTokens($tokens, 0);
        $lat = $this->parseAngleTokens($tokens, 1);

        if ($lon === null || $lat === null) {
            return null;
        }

        return new OccultationLocation(longitude: $lon, latitude: $lat);
    }

    /**
     * Parse one DMS angle. swetest may split degree and minute onto separate
     * tokens (e.g. `107°` `7'`) or keep them joined (`72°35'`). Joins them.
     */
    private function parseAngleTokens(array $tokens, int $which): ?float
    {
        // Re-join all tokens then regex out two angle groups.
        $joined = implode(' ', $tokens);
        if (preg_match_all(
            "/(-?\\d+)°\\s*(\\d+)?'?\\s*(\\d+(?:\\.\\d+)?)?\"?/u",
            $joined,
            $m,
            PREG_SET_ORDER
        )) {
            if (! isset($m[$which])) {
                return null;
            }
            $deg = (float) $m[$which][1];
            $min = isset($m[$which][2]) && $m[$which][2] !== '' ? (float) $m[$which][2] : 0.0;
            $sec = isset($m[$which][3]) && $m[$which][3] !== '' ? (float) $m[$which][3] : 0.0;
            $sign = $deg < 0 || str_starts_with(trim($m[$which][1]), '-') ? -1.0 : 1.0;

            return $sign * (abs($deg) + $min / 60 + $sec / 3600);
        }

        return null;
    }

    private function parseDeltaT(array $tokens): ?float
    {
        foreach ($tokens as $tok) {
            if (str_starts_with($tok, 'dt=')) {
                return $this->toFloat(substr($tok, 3));
            }
        }

        return null;
    }

    /**
     * "52 min 21.03 sec" → 3141.03 seconds.
     */
    private function parseDuration(array $tokens): ?float
    {
        $joined = implode(' ', $tokens);
        if (preg_match('/(\d+(?:\.\d+)?)\s*min\s*(\d+(?:\.\d+)?)\s*sec/u', $joined, $m)) {
            return ((float) $m[1]) * 60 + (float) $m[2];
        }

        return null;
    }

    /**
     * Remove the leading "N min S sec" tokens, returning the remainder.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function stripDurationTokens(array $tokens): array
    {
        // Find the index just after the "sec" token; contacts follow.
        foreach ($tokens as $idx => $tok) {
            if ($tok === 'sec') {
                return array_values(array_slice($tokens, $idx + 1));
            }
        }

        return $tokens;
    }

    private function parseType(?string $token): ?OccultationType
    {
        return $token === null ? null : OccultationType::tryFrom($token);
    }

    private function parseCentrality(?string $token): ?Centrality
    {
        return $token === null ? null : Centrality::tryFrom($token);
    }

    private function parseDateTime(?string $date, ?string $time): ?Carbon
    {
        if ($date === null || $time === null) {
            return null;
        }
        if (! preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $date)) {
            return null;
        }
        if (! $this->isTime($time)) {
            return null;
        }

        $parts = explode('.', $time, 2);
        $hms = $parts[0];
        $fracRaw = $parts[1] ?? '0';
        $micros = (int) str_pad(substr($fracRaw, 0, 6), 6, '0', STR_PAD_RIGHT);

        $dt = Carbon::createFromFormat('d.m.Y H:i:s', "{$date} {$hms}", 'UTC');
        if (! ($dt instanceof Carbon)) {
            return null;
        }

        return $dt->setMicroseconds($micros);
    }

    private function isTime(string $token): bool
    {
        return (bool) preg_match('/^\d{1,2}:\d{2}:\d{2}(\.\d+)?$/', $token);
    }

    private function toFloat(?string $token): ?float
    {
        if ($token === null || $token === '' || $token === '-') {
            return null;
        }
        if (! is_numeric($token)) {
            return null;
        }

        return (float) $token;
    }

    private function firstToken(string $line): string
    {
        return $this->tokens($line)[0] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $line): array
    {
        $tokens = preg_split('/\s+/', trim($line));

        return $tokens === false ? [] : array_values(array_filter($tokens, fn ($t) => $t !== ''));
    }
}
```

> **Angle-token caveat:** the global L3 fixture is `107° 7'` `72°35'` (degree/minute split differs between the two angles). `parseAngleTokens()` joins the whole line and regexes out the two `°`-anchored groups, so it tolerates either spacing. If the captured binary ever emits seconds (`"`), the third regex group captures them. Verify the two computed decimals against the test's `107 + 7/60` and `72 + 35/60` expectations.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationParserTest.php`
Expected: PASS (5 tests — global, local, `-` placeholder, empty, malformed-skip).

- [ ] **Step 5: Commit**

```bash
git add src/Support/Occultations/OccultationParser.php tests/Features/Support/Occultations/OccultationParserTest.php
git commit -m "feat(occultations): add block-aware OccultationParser"
```

---

### Task 8: Wire factory + facade + provider

**Files:**
- Modify: `src/Swisseph.php`
- Modify: `src/Facades/Swisseph.php`
- Modify: `src/SwissephServiceProvider.php`
- Test: `tests/Features/Support/Occultations/OccultationsFactoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder;

it('exposes a fresh occultations() sub-builder', function () {
    expect(Swisseph::occultations())->toBeInstanceOf(OccultationsBuilder::class);
});

it('returns an isolated builder on each occultations() call', function () {
    $a = Swisseph::occultations()->forStar('Aldebaran');
    $b = Swisseph::occultations();

    // $b has no target set → build() must throw.
    expect(fn () => $b->build())
        ->toThrow(\DivineaLabs\Swisseph\Exceptions\OccultationTargetNotSetException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationsFactoryTest.php`
Expected: FAIL — `occultations()` not defined on `Swisseph`.

- [ ] **Step 3: Add `occultations()` to the Swisseph factory**

In `src/Swisseph.php`, add (alongside `positions()` / `risings()`):

```php
use DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder;

// …

public function occultations(): OccultationsBuilder
{
    return app(OccultationsBuilder::class);
}
```

- [ ] **Step 4: Bind the builder + parser in the provider**

In `src/SwissephServiceProvider.php`, inside `registeringPackage()`, add:

```php
$this->app->bind(\DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder::class);
$this->app->bind(\DivineaLabs\Swisseph\Support\Occultations\OccultationParser::class);
```

> `OccultationsBuilder`'s constructor params (`SwissephExecutor`, `OccultationParser`) are nullable with `app()` fallback in `get()`, so default container resolution suffices; no factory closure needed.

- [ ] **Step 5: Add the facade `@method` annotation**

In `src/Facades/Swisseph.php`, add to the docblock:

```php
 * @method static \DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder occultations()
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationsFactoryTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add src/Swisseph.php src/Facades/Swisseph.php src/SwissephServiceProvider.php tests/Features/Support/Occultations/OccultationsFactoryTest.php
git commit -m "feat(occultations): wire occultations() factory, facade method, provider bindings"
```

---

### Task 9: Self-skipping integration test (with binary)

**Files:**
- Create: `tests/Features/Support/Occultations/OccultationsIntegrationTest.php`

This test runs the real `swetest` binary if configured + present, otherwise self-skips (same pattern as the existing rise/set integration tests).

- [ ] **Step 1: Write the integration test**

```php
<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Data\OccultationEventData;
use DivineaLabs\Swisseph\Facades\Swisseph;

beforeEach(function () {
    $exe = (string) config('swisseph.executable', '');
    if ($exe === '' || ! is_executable($exe)) {
        $this->markTestSkipped('swetest binary not available — integration test skipped.');
    }
});

it('returns a typed global occultation collection from the real binary', function () {
    $result = Swisseph::occultations()
        ->forStar('Aldebaran')
        ->global()
        ->from('2033-01-01')
        ->count(1)
        ->get();

    expect($result)->toBeInstanceOf(OccultationCollection::class);

    $first = $result->first();
    if ($first !== null) {
        expect($first)->toBeInstanceOf(OccultationEventData::class);
        expect($first->magnitude)->toBeFloat();
        expect($first->maxAt->timezoneName)->toBe('UTC');
    }
});

it('returns a typed local occultation collection from the real binary', function () {
    $result = Swisseph::occultations()
        ->forStar('Aldebaran')
        ->local(21.0, 52.2, 100.0)
        ->from('2034-01-01')
        ->count(1)
        ->get();

    expect($result)->toBeInstanceOf(OccultationCollection::class);

    $first = $result->first();
    if ($first !== null) {
        expect($first->isLocal())->toBeTrue();
        expect($first->coreShadowKm)->toBeNull();
    }
});
```

- [ ] **Step 2: Run the integration test**

Run: `vendor/bin/pest tests/Features/Support/Occultations/OccultationsIntegrationTest.php`
Expected: PASS or SKIPPED (skipped when `swetest` is absent; passes against a real binary).

- [ ] **Step 3: Commit**

```bash
git add tests/Features/Support/Occultations/OccultationsIntegrationTest.php
git commit -m "test(occultations): add self-skipping integration test"
```

---

### Task 10: Full green gate

- [ ] **Step 1: Run the full Pest suite**

Run: `vendor/bin/pest`
Expected: PASS — all existing tests plus the new occultations suite green (integration test skipped without a binary).

- [ ] **Step 2: Run static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors. Fix any type issues the new code surfaces (notably the nullable `?float`/`?Carbon` flows in the parser and the `?PlanetBody $body` narrowing in `build()`).

- [ ] **Step 3: Commit any static-analysis fixups (only if needed)**

```bash
git add -A
git commit -m "chore(occultations): satisfy phpstan"
```

---

## Self-Review

- **Spec coverage (SP2):**
  - Builder: `forStar()` → `-pf -xf<name>`, `forBody()` → `-p<value>`, `global()`/`local(float,float,float)` → `-local -geopos<lon>,<lat>,<elev>` (FLOAT params via `fmt()`), `from()` → `-b<d.m.Y>`, `count()` → `-n<count>` (default 1), `backward()` → `-bwd`, always `-occult`, typed throw at build when no target, terminal `get(): OccultationCollection`, lazy executor+parser via `app()`, `skipPrefixes(['./', 'geo. long'])`. ✅
  - Parser: block-aware — 3 lines/event global (type+centrality qualifier · date · maxTime · coreShadowKm · magnitude · JD ‖ 4 contacts · `dt=` ‖ lon · lat), 2 lines/event local (type · date · maxTime · magnitude · JD ‖ duration · 4 contacts · `dt=`); tokenize `\s+`; `-`→null; tolerant skip; NO Saros field. ✅
  - DTO: `OccultationEventData` with `type`, `centrality` (?Centrality, null when qualifier absent), `scope` (new `OccultationScope`, NOT `EclipseScope`), `maxAt` Carbon UTC, `julianDay`, `deltaT`, `magnitude`, `coreShadowKm` (?float global-only), `contacts`, `location` (?OccultationLocation global-only), `duration` (?float seconds local-only); accessors `isLocal()`, `isTotal()`. ✅
  - Value objects: `OccultationContacts` (exteriorStart/interiorStart/interiorEnd/exteriorEnd, each ?Carbon) + `OccultationLocation` (longitude/latitude). ✅
  - Collection: `OccultationCollection` with `all()` + `first()`. ✅
  - Enums: `OccultationType` (TOTAL/ANNULAR/PARTIAL), `Centrality` (CENTRAL/NON_CENTRAL), `OccultationScope` (GLOBAL/LOCAL) — all string-backed. ✅
  - Exception: `OccultationTargetNotSetException`, thrown at `build()`. ✅
  - Provider binding + `Swisseph::occultations()` + facade `@method`. ✅
  - Tests: builder (star/planet × global/local × backward × from/count × missing-target throws × eph-option), parser (EXACT captured global + local Aldebaran fixtures, every field incl. centrality qualifier, nullable `-` contacts, coreShadowKm, duration, deltaT), self-skipping integration. ✅
- **Convention fidelity:** builder `use ResolvesSwissephEnvironment`; `Swisseph::occultations()` returns a fresh container instance; parser receives pre-stripped lines via executor `skipPrefixes`; DTOs are spatie `Data`; time parsing mirrors `RiseParser` (`d.m.Y` + `H:i:s.u`, `setMicroseconds`); parser never throws (guard-and-continue). ✅
- **Self-contained:** SP2 introduces its own `OccultationScope` rather than reusing `EclipseScope`, so it does not couple to SP1. ✅
- **Open risks flagged for the implementer:**
  1. Trait property re-declaration (`$longitude`/`$latitude`/`$elevation`) — Task 6 Step 3 notes the fallback (reset in constructor) if PHP rejects the narrower re-declaration.
  2. `fmt()` trailing-zero trimming drives the exact `geopos` assertions (`21`, `52.2`, `100`/`0`) — confirm against the actual trait `fmt()` output.
  3. `EphOptions::TRUE_POSITIONS` case name — verify in `src/Enums/EphOptions.php` before running the builder test.
  4. Global L3 angle spacing (`107° 7'` vs `72°35'`) — `parseAngleTokens()` joins-and-regexes to tolerate both; verify the two decimals.
- **No commits run by the planning agent.** All commit steps are left for the executing worker.
</content>
</invoke>
