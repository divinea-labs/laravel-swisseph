# SP1 — Eclipses Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a native `eclipses()` pipeline to `laravel-swisseph` that wraps swetest's `-solecl`/`-lunecl`, exposing a fluent `EclipsesBuilder` and a block-aware `EclipseParser` that maps the exact captured swetest output into a unified `EclipseEventData` DTO stream (with named value objects), discriminated by `EclipseKind`/`EclipseType`/`EclipseScope`.

**Architecture:** Mirror the SP0 pipeline convention. `EclipsesBuilder` lives in `src/Support/Eclipses/`, `use`s `ResolvesSwissephEnvironment` for executable/ephe-dir/eph-options, and emits a `SwissephCommand` via `build()`. `Swisseph::eclipses()` returns a fresh builder from the container. The terminal `get()` lazy-resolves `SwissephExecutor` + `EclipseParser` via `app()` (same pattern as `PositionsBuilder` in SP0), declaring `skipPrefixes` `['./', 'geo. long']` so the parser receives data lines only. The parser groups multi-line records (3/2/3 lines) and branches on kind+scope.

**Tech Stack:** PHP 8.3/8.4, Laravel 11/12/13, spatie/laravel-data, spatie/laravel-package-tools, Pest 4, Symfony Process, larastan/PHPStan.

**Dependency:** SP1 depends on SP0 (`2026-06-02-SP0-foundation-restructure.md`) being fully implemented — the `ResolvesSwissephEnvironment` trait, the `SwissephExecutor::run($command, $skipPrefixes)` signature, and the `Swisseph` factory MUST already exist.

**Conventions followed (locked by SP0):**
- Pipeline in `src/Support/Eclipses/` with `EclipsesBuilder.php` + `EclipseParser.php`; DTOs under `src/Data/`; enums under `src/Enums/`; exceptions under `src/Exceptions/`.
- Builder `use`s `ResolvesSwissephEnvironment`; emits `SwissephCommand` via `build()`.
- `Swisseph::eclipses()` returns a fresh builder resolved from the container.
- Parser receives stdout lines already stripped of the command-echo + `geo. long` header by `SwissephExecutor` (builder declares `skipPrefixes`).
- Floats everywhere for geo (lon, lat, elev) — NO `GeoLocation` object (cf. `setLocation()`).
- `Carbon` (not `CarbonImmutable`), UTC, parsed via `Carbon::createFromFormat('d.m.Y H:i:s', …, 'UTC')` + microsecond padding (cf. `RiseParser`).

---

## File Structure

- Create: `src/Enums/EclipseKind.php` — `SOLAR`/`LUNAR` (string-backed).
- Create: `src/Enums/EclipseType.php` — `TOTAL`/`ANNULAR`/`PARTIAL`/`HYBRID`/`PENUMBRAL` (string-backed).
- Create: `src/Enums/EclipseScope.php` — `GLOBAL`/`LOCAL` (string-backed).
- Create: `src/Exceptions/EclipseKindNotSetException.php`.
- Create: `src/Exceptions/InvalidEclipseFilterException.php`.
- Create: `src/Data/EclipseMagnitudes.php` — value object (`primary`, `secondary`, `?tertiary`).
- Create: `src/Data/SarosSeries.php` — value object (`series:int`, `member:int`).
- Create: `src/Data/EclipseContacts.php` — value object (6 `?Carbon` phases).
- Create: `src/Data/EclipseLocation.php` — value object (`longitude:float`, `latitude:float`).
- Create: `src/Data/EclipseEventData.php` — unified DTO + accessors.
- Create: `src/Data/EclipseCollection.php` — thin `list<EclipseEventData>` wrapper.
- Create: `src/Support/Eclipses/EclipsesBuilder.php` — fluent builder, `build()` + `get()`.
- Create: `src/Support/Eclipses/EclipseParser.php` — block-aware parser.
- Modify: `src/Swisseph.php` — add `eclipses(): EclipsesBuilder`.
- Modify: `src/Facades/Swisseph.php` — add `@method` line.
- Modify: `src/SwissephServiceProvider.php` — bind `EclipsesBuilder` + `EclipseParser`.
- Create test: `tests/Features/Support/Eclipses/EclipsesBuilderTest.php`.
- Create test: `tests/Features/Support/Eclipses/EclipseParserTest.php`.
- Create test: `tests/Features/Support/Eclipses/EclipseIntegrationTest.php` — self-skips without binary.

---

### Task 1: Eclipse enums

**Files:**
- Create: `src/Enums/EclipseKind.php`
- Create: `src/Enums/EclipseType.php`
- Create: `src/Enums/EclipseScope.php`
- Test: `tests/Features/Support/Eclipses/EclipseEnumsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;

it('exposes solar and lunar kinds', function () {
    expect(EclipseKind::SOLAR->value)->toBe('solar');
    expect(EclipseKind::LUNAR->value)->toBe('lunar');
});

it('maps swetest type words via fromToken', function () {
    expect(EclipseType::fromToken('annular'))->toBe(EclipseType::ANNULAR);
    expect(EclipseType::fromToken('total'))->toBe(EclipseType::TOTAL);
    expect(EclipseType::fromToken('partial'))->toBe(EclipseType::PARTIAL);
    expect(EclipseType::fromToken('penumbral'))->toBe(EclipseType::PENUMBRAL);
    expect(EclipseType::fromToken('anntot'))->toBe(EclipseType::HYBRID);
    expect(EclipseType::fromToken('hybrid'))->toBe(EclipseType::HYBRID);
    expect(EclipseType::fromToken('nope'))->toBeNull();
});

it('reports the known type tokens', function () {
    expect(EclipseType::isTypeToken('total'))->toBeTrue();
    expect(EclipseType::isTypeToken('saros'))->toBeFalse();
});

it('exposes global and local scopes', function () {
    expect(EclipseScope::GLOBAL->value)->toBe('global');
    expect(EclipseScope::LOCAL->value)->toBe('local');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseEnumsTest.php`
Expected: FAIL — enums not found.

- [ ] **Step 3: Write the enums**

`src/Enums/EclipseKind.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum EclipseKind: string
{
    case SOLAR = 'solar';
    case LUNAR = 'lunar';
}
```

`src/Enums/EclipseScope.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum EclipseScope: string
{
    case GLOBAL = 'global';
    case LOCAL = 'local';
}
```

`src/Enums/EclipseType.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum EclipseType: string
{
    case TOTAL = 'total';
    case ANNULAR = 'annular';
    case PARTIAL = 'partial';
    case HYBRID = 'hybrid';
    case PENUMBRAL = 'penumbral';

    /**
     * Map a swetest first-token type word onto the enum.
     * swetest emits `anntot` for hybrid (annular-total) eclipses.
     */
    public static function fromToken(string $token): ?self
    {
        return match (strtolower(trim($token))) {
            'total' => self::TOTAL,
            'annular' => self::ANNULAR,
            'partial' => self::PARTIAL,
            'penumbral' => self::PENUMBRAL,
            'anntot', 'hybrid' => self::HYBRID,
            default => null,
        };
    }

    public static function isTypeToken(string $token): bool
    {
        return self::fromToken($token) !== null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseEnumsTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Enums/EclipseKind.php src/Enums/EclipseType.php src/Enums/EclipseScope.php tests/Features/Support/Eclipses/EclipseEnumsTest.php
git commit -m "feat(eclipses): add EclipseKind/EclipseType/EclipseScope enums"
```

---

### Task 2: Eclipse exceptions

**Files:**
- Create: `src/Exceptions/EclipseKindNotSetException.php`
- Create: `src/Exceptions/InvalidEclipseFilterException.php`
- Test: `tests/Features/Support/Eclipses/EclipseExceptionsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Exceptions\EclipseKindNotSetException;
use DivineaLabs\Swisseph\Exceptions\InvalidEclipseFilterException;

it('builds a kind-not-set exception', function () {
    $e = EclipseKindNotSetException::missing();
    expect($e)->toBeInstanceOf(InvalidArgumentException::class);
    expect($e->getMessage())->toContain('solar()');
});

it('builds an invalid-filter exception naming the filter and kind', function () {
    $e = InvalidEclipseFilterException::notAllowed('onlyAnnular', EclipseKind::LUNAR);
    expect($e)->toBeInstanceOf(InvalidArgumentException::class);
    expect($e->getMessage())->toContain('onlyAnnular');
    expect($e->getMessage())->toContain('lunar');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseExceptionsTest.php`
Expected: FAIL — exceptions not found.

- [ ] **Step 3: Write the exceptions**

`src/Exceptions/EclipseKindNotSetException.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use InvalidArgumentException;

final class EclipseKindNotSetException extends InvalidArgumentException
{
    public static function missing(): self
    {
        return new self('Eclipse kind not set — call solar() or lunar() before get().');
    }
}
```

`src/Exceptions/InvalidEclipseFilterException.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

use DivineaLabs\Swisseph\Enums\EclipseKind;
use InvalidArgumentException;

final class InvalidEclipseFilterException extends InvalidArgumentException
{
    public static function notAllowed(string $filter, EclipseKind $kind): self
    {
        return new self(sprintf(
            'Filter %s() is not valid for %s eclipses.',
            $filter,
            $kind->value,
        ));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseExceptionsTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Exceptions/EclipseKindNotSetException.php src/Exceptions/InvalidEclipseFilterException.php tests/Features/Support/Eclipses/EclipseExceptionsTest.php
git commit -m "feat(eclipses): add EclipseKindNotSet/InvalidEclipseFilter exceptions"
```

---

### Task 3: Value objects (EclipseMagnitudes, SarosSeries, EclipseLocation)

**Files:**
- Create: `src/Data/EclipseMagnitudes.php`
- Create: `src/Data/SarosSeries.php`
- Create: `src/Data/EclipseLocation.php`
- Test: `tests/Features/Support/Eclipses/EclipseValueObjectsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Data\EclipseLocation;
use DivineaLabs\Swisseph\Data\EclipseMagnitudes;
use DivineaLabs\Swisseph\Data\SarosSeries;

it('holds three solar magnitudes', function () {
    $m = new EclipseMagnitudes(0.9638, 0.9797, 0.9288);
    expect($m->primary)->toBe(0.9638);
    expect($m->secondary)->toBe(0.9797);
    expect($m->tertiary)->toBe(0.9288);
});

it('holds two lunar magnitudes with null tertiary', function () {
    $m = new EclipseMagnitudes(1.1507, 2.1839, null);
    expect($m->primary)->toBe(1.1507);
    expect($m->secondary)->toBe(2.1839);
    expect($m->tertiary)->toBeNull();
});

it('holds a saros series and member', function () {
    $s = new SarosSeries(133, 27);
    expect($s->series)->toBe(133);
    expect($s->member)->toBe(27);
});

it('holds an eclipse location', function () {
    $l = new EclipseLocation(-170.6122, 6.4017);
    expect($l->longitude)->toBe(-170.6122);
    expect($l->latitude)->toBe(6.4017);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseValueObjectsTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the value objects**

`src/Data/EclipseMagnitudes.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class EclipseMagnitudes extends Data
{
    public function __construct(
        public readonly float $primary,
        public readonly float $secondary,
        public readonly ?float $tertiary = null,
    ) {}
}
```

`src/Data/SarosSeries.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class SarosSeries extends Data
{
    public function __construct(
        public readonly int $series,
        public readonly int $member,
    ) {}
}
```

`src/Data/EclipseLocation.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class EclipseLocation extends Data
{
    public function __construct(
        public readonly float $longitude,
        public readonly float $latitude,
    ) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseValueObjectsTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Data/EclipseMagnitudes.php src/Data/SarosSeries.php src/Data/EclipseLocation.php tests/Features/Support/Eclipses/EclipseValueObjectsTest.php
git commit -m "feat(eclipses): add EclipseMagnitudes/SarosSeries/EclipseLocation value objects"
```

---

### Task 4: EclipseContacts value object

**Files:**
- Create: `src/Data/EclipseContacts.php`
- Test: `tests/Features/Support/Eclipses/EclipseContactsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseContacts;

it('holds six nullable lunar contacts', function () {
    $c = new EclipseContacts(
        penumbralStart: Carbon::parse('2026-03-03 08:44:21', 'UTC'),
        partialStart: Carbon::parse('2026-03-03 09:50:02', 'UTC'),
        totalStart: Carbon::parse('2026-03-03 11:04:30', 'UTC'),
        totalEnd: Carbon::parse('2026-03-03 12:02:49', 'UTC'),
        partialEnd: Carbon::parse('2026-03-03 13:17:15', 'UTC'),
        penumbralEnd: Carbon::parse('2026-03-03 14:23:05', 'UTC'),
    );

    expect($c->penumbralStart->format('H:i:s'))->toBe('08:44:21');
    expect($c->penumbralEnd->format('H:i:s'))->toBe('14:23:05');
});

it('leaves inapplicable contacts null (solar maps four)', function () {
    $c = new EclipseContacts(
        penumbralStart: null,
        partialStart: Carbon::parse('2026-02-17 09:56:44', 'UTC'),
        totalStart: Carbon::parse('2026-02-17 11:43:01', 'UTC'),
        totalEnd: Carbon::parse('2026-02-17 12:41:02', 'UTC'),
        partialEnd: Carbon::parse('2026-02-17 14:27:37', 'UTC'),
        penumbralEnd: null,
    );

    expect($c->penumbralStart)->toBeNull();
    expect($c->penumbralEnd)->toBeNull();
    expect($c->partialStart->format('H:i:s'))->toBe('09:56:44');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseContactsTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write EclipseContacts**

`src/Data/EclipseContacts.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class EclipseContacts extends Data
{
    /**
     * Six named contact phases, each nullable (UTC).
     *
     * Lunar uses all six. Solar maps its four contacts onto
     * partialStart/totalStart/totalEnd/partialEnd (penumbral slots null).
     * Local/partial eclipses leave inapplicable slots null (the `-` placeholders).
     */
    public function __construct(
        public readonly ?Carbon $penumbralStart,
        public readonly ?Carbon $partialStart,
        public readonly ?Carbon $totalStart,
        public readonly ?Carbon $totalEnd,
        public readonly ?Carbon $partialEnd,
        public readonly ?Carbon $penumbralEnd,
    ) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseContactsTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Data/EclipseContacts.php tests/Features/Support/Eclipses/EclipseContactsTest.php
git commit -m "feat(eclipses): add EclipseContacts value object"
```

---

### Task 5: EclipseEventData DTO + accessors

**Files:**
- Create: `src/Data/EclipseEventData.php`
- Test: `tests/Features/Support/Eclipses/EclipseEventDataTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseContacts;
use DivineaLabs\Swisseph\Data\EclipseEventData;
use DivineaLabs\Swisseph\Data\EclipseLocation;
use DivineaLabs\Swisseph\Data\EclipseMagnitudes;
use DivineaLabs\Swisseph\Data\SarosSeries;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;

function makeSolarGlobalEvent(): EclipseEventData
{
    return new EclipseEventData(
        kind: EclipseKind::SOLAR,
        type: EclipseType::TOTAL,
        scope: EclipseScope::GLOBAL,
        maxAt: Carbon::parse('2026-08-12 17:45:56', 'UTC'),
        julianDay: 2461265.240240,
        deltaT: 71.3,
        magnitudes: new EclipseMagnitudes(1.0395, 1.0178, 1.0806),
        saros: new SarosSeries(126, 48),
        contacts: new EclipseContacts(null, null, null, null, null, null),
        location: new EclipseLocation(-25.0944, 65.1652),
        coreShadowKm: -132.445818,
        duration: 138.03,
    );
}

it('reports solar / total / global accessors', function () {
    $e = makeSolarGlobalEvent();
    expect($e->isSolar())->toBeTrue();
    expect($e->isLunar())->toBeFalse();
    expect($e->isTotal())->toBeTrue();
    expect($e->isLocal())->toBeFalse();
});

it('reports lunar and local accessors', function () {
    $e = new EclipseEventData(
        kind: EclipseKind::LUNAR,
        type: EclipseType::PARTIAL,
        scope: EclipseScope::LOCAL,
        maxAt: Carbon::parse('2026-08-28 04:12:55', 'UTC'),
        julianDay: 2461280.675642,
        deltaT: 71.3,
        magnitudes: new EclipseMagnitudes(0.9299, 1.9646, null),
        saros: new SarosSeries(138, 29),
        contacts: new EclipseContacts(null, null, null, null, null, null),
        location: null,
        coreShadowKm: null,
        duration: null,
    );

    expect($e->isLunar())->toBeTrue();
    expect($e->isSolar())->toBeFalse();
    expect($e->isTotal())->toBeFalse();
    expect($e->isLocal())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseEventDataTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write EclipseEventData**

`src/Data/EclipseEventData.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;
use Spatie\LaravelData\Data;

class EclipseEventData extends Data
{
    public function __construct(
        public readonly EclipseKind $kind,
        public readonly EclipseType $type,
        public readonly EclipseScope $scope,
        public readonly Carbon $maxAt,
        public readonly float $julianDay,
        public readonly float $deltaT,
        public readonly EclipseMagnitudes $magnitudes,
        public readonly SarosSeries $saros,
        public readonly EclipseContacts $contacts,
        public readonly ?EclipseLocation $location,
        public readonly ?float $coreShadowKm,
        public readonly ?float $duration,
    ) {}

    public function isSolar(): bool
    {
        return $this->kind === EclipseKind::SOLAR;
    }

    public function isLunar(): bool
    {
        return $this->kind === EclipseKind::LUNAR;
    }

    public function isTotal(): bool
    {
        return $this->type === EclipseType::TOTAL;
    }

    public function isLocal(): bool
    {
        return $this->scope === EclipseScope::LOCAL;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseEventDataTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Data/EclipseEventData.php tests/Features/Support/Eclipses/EclipseEventDataTest.php
git commit -m "feat(eclipses): add EclipseEventData DTO with accessors"
```

---

### Task 6: EclipseCollection wrapper

**Files:**
- Create: `src/Data/EclipseCollection.php`
- Test: `tests/Features/Support/Eclipses/EclipseCollectionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseCollection;
use DivineaLabs\Swisseph\Data\EclipseContacts;
use DivineaLabs\Swisseph\Data\EclipseEventData;
use DivineaLabs\Swisseph\Data\EclipseMagnitudes;
use DivineaLabs\Swisseph\Data\SarosSeries;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;

function makeEvent(EclipseKind $kind): EclipseEventData
{
    return new EclipseEventData(
        kind: $kind,
        type: EclipseType::TOTAL,
        scope: EclipseScope::GLOBAL,
        maxAt: Carbon::parse('2026-01-01 00:00:00', 'UTC'),
        julianDay: 2461000.0,
        deltaT: 71.0,
        magnitudes: new EclipseMagnitudes(1.0, 1.0, null),
        saros: new SarosSeries(1, 1),
        contacts: new EclipseContacts(null, null, null, null, null, null),
        location: null,
        coreShadowKm: null,
        duration: null,
    );
}

it('returns all events and the first', function () {
    $solar = makeEvent(EclipseKind::SOLAR);
    $lunar = makeEvent(EclipseKind::LUNAR);
    $c = new EclipseCollection([$solar, $lunar]);

    expect($c->all())->toBe([$solar, $lunar]);
    expect($c->first())->toBe($solar);
});

it('filters by kind', function () {
    $solar = makeEvent(EclipseKind::SOLAR);
    $lunar = makeEvent(EclipseKind::LUNAR);
    $c = new EclipseCollection([$solar, $lunar]);

    expect($c->solarOnly())->toBe([$solar]);
    expect($c->lunarOnly())->toBe([$lunar]);
});

it('returns null first on empty collection', function () {
    $c = new EclipseCollection([]);
    expect($c->all())->toBe([]);
    expect($c->first())->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseCollectionTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write EclipseCollection**

`src/Data/EclipseCollection.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use DivineaLabs\Swisseph\Enums\EclipseKind;

final class EclipseCollection
{
    /**
     * @param  list<EclipseEventData>  $events
     */
    public function __construct(
        private readonly array $events = [],
    ) {}

    /**
     * @return list<EclipseEventData>
     */
    public function all(): array
    {
        return $this->events;
    }

    public function first(): ?EclipseEventData
    {
        return $this->events[0] ?? null;
    }

    /**
     * @return list<EclipseEventData>
     */
    public function solarOnly(): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (EclipseEventData $e) => $e->kind === EclipseKind::SOLAR,
        ));
    }

    /**
     * @return list<EclipseEventData>
     */
    public function lunarOnly(): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (EclipseEventData $e) => $e->kind === EclipseKind::LUNAR,
        ));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseCollectionTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Data/EclipseCollection.php tests/Features/Support/Eclipses/EclipseCollectionTest.php
git commit -m "feat(eclipses): add EclipseCollection wrapper"
```

---

### Task 7: EclipsesBuilder — argument construction

**Files:**
- Create: `src/Support/Eclipses/EclipsesBuilder.php`
- Test: `tests/Features/Support/Eclipses/EclipsesBuilderTest.php`

The builder is fluent and immutable-feeling (returns `$this` from setters; `Swisseph::eclipses()` always hands back a fresh instance per SP0). It `use`s `ResolvesSwissephEnvironment` for `executable`/`epheDirArg()`/`ephOptionArgs()`/`fmt()`. Kind defaults to unset → `EclipseKindNotSetException` at `build()`. Scope defaults to GLOBAL. `count` defaults to 1. Filters validated against kind at `build()`.

Filter → kind validity matrix (enforced in `build()`):
- Lunar-valid: `onlyTotal`, `onlyPartial`, `onlyPenumbral`. (`onlyAnnular`, `onlyHybrid`, `onlyCentral`, `onlyNonCentral` invalid on lunar.)
- Solar-valid: `onlyTotal`, `onlyPartial`, `onlyAnnular`, `onlyHybrid`, `onlyCentral`, `onlyNonCentral`. (`onlyPenumbral` invalid on solar.)

Argument order in `build()`: `edir…`, eph-options, `solecl`|`lunecl`, (if local) `local`, `geopos<lon>,<lat>,<elev>`, `b<d.m.Y>`, `n<count>`, (if backward) `bwd`, then any type-filter flags in declaration order.

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Exceptions\EclipseKindNotSetException;
use DivineaLabs\Swisseph\Exceptions\InvalidEclipseFilterException;
use DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder;

beforeEach(function () {
    config()->set('swisseph.executable', '/bin/swetest');
    config()->set('swisseph.ephemeris_dir', '/ephe');
    config()->set('swisseph.eph_options', []);
});

function eclipseArgs(EclipsesBuilder $b): array
{
    return $b->build()->arguments;
}

it('throws when kind is not set', function () {
    (new EclipsesBuilder())->from('2026-01-01')->build();
})->throws(EclipseKindNotSetException::class);

it('builds solar global args with defaults (count 1)', function () {
    $b = (new EclipsesBuilder())->solar()->from('2026-01-01');

    expect(eclipseArgs($b))->toBe([
        'edir/ephe',
        'solecl',
        'b01.01.2026',
        'n1',
    ]);
});

it('builds lunar global args with count and backward', function () {
    $b = (new EclipsesBuilder())->lunar()->from('2026-01-01')->count(5)->backward();

    expect(eclipseArgs($b))->toBe([
        'edir/ephe',
        'lunecl',
        'b01.01.2026',
        'n5',
        'bwd',
    ]);
});

it('builds solar local args with geopos floats', function () {
    $b = (new EclipsesBuilder())->solar()->local(21.0, 52.2, 100.0)->from('2026-08-01')->count(2);

    expect(eclipseArgs($b))->toBe([
        'edir/ephe',
        'solecl',
        'local',
        'geopos21,52.2,100',
        'b01.08.2026',
        'n2',
    ]);
});

it('defaults elevation to zero in local geopos', function () {
    $b = (new EclipsesBuilder())->lunar()->local(21.0, 52.2)->from('2026-08-01');

    expect(eclipseArgs($b))->toContain('geopos21,52.2,0');
});

it('emits solar type filters', function () {
    expect(eclipseArgs((new EclipsesBuilder())->solar()->from('2026-01-01')->onlyTotal()))->toContain('total');
    expect(eclipseArgs((new EclipsesBuilder())->solar()->from('2026-01-01')->onlyPartial()))->toContain('partial');
    expect(eclipseArgs((new EclipsesBuilder())->solar()->from('2026-01-01')->onlyAnnular()))->toContain('annular');
    expect(eclipseArgs((new EclipsesBuilder())->solar()->from('2026-01-01')->onlyHybrid()))->toContain('anntot');
    expect(eclipseArgs((new EclipsesBuilder())->solar()->global()->from('2026-01-01')->onlyCentral()))->toContain('central');
    expect(eclipseArgs((new EclipsesBuilder())->solar()->global()->from('2026-01-01')->onlyNonCentral()))->toContain('noncentral');
});

it('emits lunar type filters', function () {
    expect(eclipseArgs((new EclipsesBuilder())->lunar()->from('2026-01-01')->onlyTotal()))->toContain('total');
    expect(eclipseArgs((new EclipsesBuilder())->lunar()->from('2026-01-01')->onlyPartial()))->toContain('partial');
    expect(eclipseArgs((new EclipsesBuilder())->lunar()->from('2026-01-01')->onlyPenumbral()))->toContain('penumbral');
});

it('rejects annular on lunar', function () {
    (new EclipsesBuilder())->lunar()->from('2026-01-01')->onlyAnnular()->build();
})->throws(InvalidEclipseFilterException::class);

it('rejects hybrid on lunar', function () {
    (new EclipsesBuilder())->lunar()->from('2026-01-01')->onlyHybrid()->build();
})->throws(InvalidEclipseFilterException::class);

it('rejects central on lunar', function () {
    (new EclipsesBuilder())->lunar()->from('2026-01-01')->onlyCentral()->build();
})->throws(InvalidEclipseFilterException::class);

it('rejects noncentral on lunar', function () {
    (new EclipsesBuilder())->lunar()->from('2026-01-01')->onlyNonCentral()->build();
})->throws(InvalidEclipseFilterException::class);

it('rejects penumbral on solar', function () {
    (new EclipsesBuilder())->solar()->from('2026-01-01')->onlyPenumbral()->build();
})->throws(InvalidEclipseFilterException::class);

it('keeps the configured executable', function () {
    expect((new EclipsesBuilder())->solar()->from('2026-01-01')->build()->executable)->toBe('/bin/swetest');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipsesBuilderTest.php`
Expected: FAIL — `EclipsesBuilder` not found.

- [ ] **Step 3: Write the builder**

`src/Support/Eclipses/EclipsesBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Eclipses;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseCollection;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Exceptions\EclipseKindNotSetException;
use DivineaLabs\Swisseph\Exceptions\InvalidEclipseFilterException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

class EclipsesBuilder
{
    use ResolvesSwissephEnvironment;

    private ?EclipseKind $kind = null;

    private EclipseScope $scope = EclipseScope::GLOBAL;

    private float $localLongitude = 0.0;

    private float $localLatitude = 0.0;

    private float $localElevation = 0.0;

    private Carbon $from;

    private int $count = 1;

    private bool $backward = false;

    /** @var list<string> swetest filter flags in declaration order */
    private array $filters = [];

    /** @var list<string> the builder-method name behind each filter flag (for error messages) */
    private array $filterMethods = [];

    public function __construct(
        private readonly ?SwissephExecutor $executor = null,
        private readonly ?EclipseParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
        $this->from = Carbon::now()->utc();
    }

    public function solar(): static
    {
        $this->kind = EclipseKind::SOLAR;

        return $this;
    }

    public function lunar(): static
    {
        $this->kind = EclipseKind::LUNAR;

        return $this;
    }

    public function global(): static
    {
        $this->scope = EclipseScope::GLOBAL;

        return $this;
    }

    public function local(float $lon, float $lat, float $elev = 0.0): static
    {
        $this->scope = EclipseScope::LOCAL;
        $this->localLongitude = $lon;
        $this->localLatitude = $lat;
        $this->localElevation = $elev;

        return $this;
    }

    public function from(Carbon|string $date): static
    {
        $this->from = is_string($date) ? Carbon::parse($date, 'UTC') : $date->copy()->utc();

        return $this;
    }

    public function count(int $count): static
    {
        $this->count = $count;

        return $this;
    }

    public function backward(): static
    {
        $this->backward = true;

        return $this;
    }

    public function onlyTotal(): static
    {
        return $this->addFilter('total', 'onlyTotal');
    }

    public function onlyPartial(): static
    {
        return $this->addFilter('partial', 'onlyPartial');
    }

    public function onlyAnnular(): static
    {
        return $this->addFilter('annular', 'onlyAnnular');
    }

    public function onlyHybrid(): static
    {
        return $this->addFilter('anntot', 'onlyHybrid');
    }

    public function onlyPenumbral(): static
    {
        return $this->addFilter('penumbral', 'onlyPenumbral');
    }

    public function onlyCentral(): static
    {
        return $this->addFilter('central', 'onlyCentral');
    }

    public function onlyNonCentral(): static
    {
        return $this->addFilter('noncentral', 'onlyNonCentral');
    }

    private function addFilter(string $flag, string $method): static
    {
        $this->filters[] = $flag;
        $this->filterMethods[] = $method;

        return $this;
    }

    public function build(): SwissephCommand
    {
        if ($this->kind === null) {
            throw EclipseKindNotSetException::missing();
        }

        $this->validateFilters($this->kind);

        $arguments = [$this->epheDirArg()];

        foreach ($this->ephOptionArgs() as $opt) {
            $arguments[] = $opt;
        }

        $arguments[] = $this->kind === EclipseKind::SOLAR ? 'solecl' : 'lunecl';

        if ($this->scope === EclipseScope::LOCAL) {
            $arguments[] = 'local';
            $arguments[] = sprintf(
                'geopos%s,%s,%s',
                $this->fmt($this->localLongitude),
                $this->fmt($this->localLatitude),
                $this->fmt($this->localElevation),
            );
        }

        $arguments[] = 'b'.$this->from->format('d.m.Y');
        $arguments[] = 'n'.$this->count;

        if ($this->backward) {
            $arguments[] = 'bwd';
        }

        foreach ($this->filters as $filter) {
            $arguments[] = $filter;
        }

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $arguments,
        );
    }

    private function validateFilters(EclipseKind $kind): void
    {
        $lunarValid = ['onlyTotal', 'onlyPartial', 'onlyPenumbral'];
        $solarValid = ['onlyTotal', 'onlyPartial', 'onlyAnnular', 'onlyHybrid', 'onlyCentral', 'onlyNonCentral'];
        $allowed = $kind === EclipseKind::LUNAR ? $lunarValid : $solarValid;

        foreach ($this->filterMethods as $method) {
            if (! in_array($method, $allowed, true)) {
                throw InvalidEclipseFilterException::notAllowed($method, $kind);
            }
        }
    }

    public function get(): EclipseCollection
    {
        $command = $this->build();

        $executor = $this->executor ?? app(SwissephExecutor::class);
        $parser = $this->parser ?? app(EclipseParser::class);

        $lines = $executor->run($command, ['./', 'geo. long']);

        return $parser->parse($lines, $this->kind ?? EclipseKind::SOLAR, $this->scope);
    }

    public function getCliCommand(): string
    {
        return $this->build()->toCliString();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipsesBuilderTest.php`
Expected: PASS (all argument/exception cases).

- [ ] **Step 5: Commit**

```bash
git add src/Support/Eclipses/EclipsesBuilder.php tests/Features/Support/Eclipses/EclipsesBuilderTest.php
git commit -m "feat(eclipses): add EclipsesBuilder with kind/scope/filter argument construction"
```

> Note: the `EclipseParser` type-hint in the constructor and `get()` is satisfied in Task 8. If running this task before Task 8, temporarily comment the `get()`/`getCliCommand()` parser usage OR implement Task 8 first — the BuilderTest only exercises `build()`, which has no parser dependency. Recommended: implement Task 8 immediately after so the class is whole.

---

### Task 8: EclipseParser — block-aware parsing of real swetest output

**Files:**
- Create: `src/Support/Eclipses/EclipseParser.php`
- Test: `tests/Features/Support/Eclipses/EclipseParserTest.php`

The parser receives lines already stripped of the echo (`./…`) and `geo. long …` header by the executor. It groups remaining lines into records: a record STARTS at any line whose first whitespace-token is a known type word (`total`/`annular`/`partial`/`penumbral`/`anntot`/`hybrid`). It then consumes the expected number of follow-up lines for the (kind, scope) combination:
- solar global: 3 lines, lunar global: 3 lines, solar local: 2 lines.

(Local lunar is not part of the SP1 captured reference and is not produced here; the parser still groups by record-start so an unexpected shape is skipped tolerantly.)

Tokenization is on `/\s+/`. `-` → null. Helpers: date+time → Carbon UTC (microsecond padding like `RiseParser`); `m1/m2/m3` split on `/`; `saros <ser>/<num>`; `dt=<x>`; `<deg>°<min>'<sec>"` → decimal degrees; `<n> min <s> sec` → seconds. No events → empty collection; malformed record → skip.

The fixtures below are the EXACT blocks from `swetest-output-reference.md` (echo/geo-header lines already removed, as the executor would).

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;
use DivineaLabs\Swisseph\Support\Eclipses\EclipseParser;

/**
 * Helper: split a fixture string into trimmed, non-empty lines,
 * matching what SwissephExecutor hands the parser (echo + geo header already stripped).
 */
function eclipseLines(string $fixture): array
{
    $lines = preg_split("/\R/u", trim($fixture)) ?: [];
    $lines = array_map('trim', $lines);

    return array_values(array_filter($lines, static fn ($l) => $l !== ''));
}

it('returns an empty collection for no lines', function () {
    $c = (new EclipseParser())->parse([], EclipseKind::SOLAR, EclipseScope::GLOBAL);
    expect($c->all())->toBe([]);
});

it('parses two solar global eclipses field-by-field', function () {
    // EXACT capture: SP1 "Solar, global (-solecl -n2)" — 3 lines/event.
    $fixture = <<<TXT
annular solar	17.02.2026	  12:11:51.1	131.068478 km	0.9638/0.9797/0.9288	saros 121/61	2461089.008230
	  09:56:44.8    11:43:01.9    12:41:02.0    14:27:37.3 dt=71.1
	  87° 4' 6"	 -64°41' 2"	2 min 19.42 sec
total solar	12.08.2026	  17:45:56.8	-132.445818 km	1.0395/1.0178/1.0806	saros 126/48	2461265.240240
	  15:34:30.7    16:58:06.9    18:33:58.2    19:57:55.9 dt=71.3
	 -25° 5'40"	  65° 9'55"	2 min 18.03 sec
TXT;

    $events = (new EclipseParser())
        ->parse(eclipseLines($fixture), EclipseKind::SOLAR, EclipseScope::GLOBAL)
        ->all();

    expect($events)->toHaveCount(2);

    $a = $events[0];
    expect($a->kind)->toBe(EclipseKind::SOLAR);
    expect($a->type)->toBe(EclipseType::ANNULAR);
    expect($a->scope)->toBe(EclipseScope::GLOBAL);
    expect($a->maxAt->format('Y-m-d H:i:s'))->toBe('2026-02-17 12:11:51');
    expect($a->julianDay)->toBe(2461089.008230);
    expect($a->coreShadowKm)->toBe(131.068478);
    expect($a->magnitudes->primary)->toBe(0.9638);
    expect($a->magnitudes->secondary)->toBe(0.9797);
    expect($a->magnitudes->tertiary)->toBe(0.9288);
    expect($a->saros->series)->toBe(121);
    expect($a->saros->member)->toBe(61);
    expect($a->deltaT)->toBe(71.1);
    // Solar maps 4 contacts onto partialStart/totalStart/totalEnd/partialEnd
    expect($a->contacts->penumbralStart)->toBeNull();
    expect($a->contacts->penumbralEnd)->toBeNull();
    expect($a->contacts->partialStart->format('H:i:s'))->toBe('09:56:44');
    expect($a->contacts->totalStart->format('H:i:s'))->toBe('11:43:01');
    expect($a->contacts->totalEnd->format('H:i:s'))->toBe('12:41:02');
    expect($a->contacts->partialEnd->format('H:i:s'))->toBe('14:27:37');
    // L3: central line lon/lat + duration "2 min 19.42 sec" → 139.42 s
    expect(round($a->location->longitude, 4))->toBe(round(87 + 4 / 60 + 6 / 3600, 4));
    expect(round($a->location->latitude, 4))->toBe(round(-(64 + 41 / 60 + 2 / 3600), 4));
    expect($a->duration)->toBe(139.42);

    $b = $events[1];
    expect($b->type)->toBe(EclipseType::TOTAL);
    expect($b->coreShadowKm)->toBe(-132.445818);
    expect($b->magnitudes->primary)->toBe(1.0395);
    expect($b->saros->series)->toBe(126);
    expect($b->saros->member)->toBe(48);
    expect($b->duration)->toBe(138.03);
    expect(round($b->location->latitude, 4))->toBe(round(65 + 9 / 60 + 55 / 3600, 4));
});

it('parses solar local eclipses with `-` placeholder contacts', function () {
    // EXACT capture: SP1 "Solar, local" — 2 lines/event (geo header already stripped).
    $fixture = <<<TXT
partial 12.08.2026	  18:03:06.0	0.8511/0.8511/0.8190	saros 126/48	2461265.252152
	0 min 0.00 sec	  17:14:40.3     -            -            -         dt=71.3
partial  2.08.2027	  09:22:21.3	0.4127/0.4127/0.3038	saros 136/38	2461619.890525
	0 min 0.00 sec	  08:26:19.3     -            -           10:19:20.8  dt=71.8
TXT;

    $events = (new EclipseParser())
        ->parse(eclipseLines($fixture), EclipseKind::SOLAR, EclipseScope::LOCAL)
        ->all();

    expect($events)->toHaveCount(2);

    $a = $events[0];
    expect($a->kind)->toBe(EclipseKind::SOLAR);
    expect($a->type)->toBe(EclipseType::PARTIAL);
    expect($a->scope)->toBe(EclipseScope::LOCAL);
    expect($a->maxAt->format('Y-m-d H:i:s'))->toBe('2026-08-12 18:03:06');
    expect($a->julianDay)->toBe(2461265.252152);
    expect($a->coreShadowKm)->toBeNull();      // no km column in local
    expect($a->magnitudes->primary)->toBe(0.8511);
    expect($a->magnitudes->secondary)->toBe(0.8511);
    expect($a->magnitudes->tertiary)->toBe(0.8190);
    expect($a->saros->series)->toBe(126);
    expect($a->saros->member)->toBe(48);
    expect($a->deltaT)->toBe(71.3);
    expect($a->duration)->toBe(0.0);            // "0 min 0.00 sec"
    // L2 contacts: partialStart visible, others `-` (null)
    expect($a->contacts->partialStart->format('H:i:s'))->toBe('17:14:40');
    expect($a->contacts->totalStart)->toBeNull();
    expect($a->contacts->totalEnd)->toBeNull();
    expect($a->contacts->partialEnd)->toBeNull();
    expect($a->location)->toBeNull();           // local has no L3 location

    $b = $events[1];
    expect($b->maxAt->format('Y-m-d H:i:s'))->toBe('2027-08-02 09:22:21');
    expect($b->deltaT)->toBe(71.8);
    expect($b->contacts->partialStart->format('H:i:s'))->toBe('08:26:19');
    expect($b->contacts->totalStart)->toBeNull();
    expect($b->contacts->totalEnd)->toBeNull();
    expect($b->contacts->partialEnd->format('H:i:s'))->toBe('10:19:20');
});

it('parses lunar global eclipses incl. a partial with `-` contacts', function () {
    // EXACT capture: SP1 "Lunar, global (-lunecl -n2)" — 3 lines/event.
    $fixture = <<<TXT
total lunar eclipse	 3.03.2026	  11:33:39.0	1.1507/2.1839	saros 133/27	2461102.981702
    08:44:21.8    09:50:02.9    11:04:30.1    12:02:49.3    13:17:15.5    14:23:05.3 dt=71.1
	-170°36'44"	   6°24' 6"
partial lunar eclipse	28.08.2026	  04:12:55.5	0.9299/1.9646	saros 138/29	2461280.675642
    01:23:55.2    02:33:50.3     -            -           05:52:00.3    07:01:47.1 dt=71.3
	 -63° 6'38"	  -9°18' 3"
TXT;

    $events = (new EclipseParser())
        ->parse(eclipseLines($fixture), EclipseKind::LUNAR, EclipseScope::GLOBAL)
        ->all();

    expect($events)->toHaveCount(2);

    $a = $events[0];
    expect($a->kind)->toBe(EclipseKind::LUNAR);
    expect($a->type)->toBe(EclipseType::TOTAL);
    expect($a->scope)->toBe(EclipseScope::GLOBAL);
    expect($a->maxAt->format('Y-m-d H:i:s'))->toBe('2026-03-03 11:33:39');
    expect($a->julianDay)->toBe(2461102.981702);
    expect($a->coreShadowKm)->toBeNull();
    expect($a->duration)->toBeNull();           // lunar has no duration
    expect($a->magnitudes->primary)->toBe(1.1507);   // umbral
    expect($a->magnitudes->secondary)->toBe(2.1839); // penumbral
    expect($a->magnitudes->tertiary)->toBeNull();
    expect($a->saros->series)->toBe(133);
    expect($a->saros->member)->toBe(27);
    expect($a->deltaT)->toBe(71.1);
    // L2: 6 contacts, all present
    expect($a->contacts->penumbralStart->format('H:i:s'))->toBe('08:44:21');
    expect($a->contacts->partialStart->format('H:i:s'))->toBe('09:50:02');
    expect($a->contacts->totalStart->format('H:i:s'))->toBe('11:04:30');
    expect($a->contacts->totalEnd->format('H:i:s'))->toBe('12:02:49');
    expect($a->contacts->partialEnd->format('H:i:s'))->toBe('13:17:15');
    expect($a->contacts->penumbralEnd->format('H:i:s'))->toBe('14:23:05');
    expect(round($a->location->longitude, 4))->toBe(round(-(170 + 36 / 60 + 44 / 3600), 4));
    expect(round($a->location->latitude, 4))->toBe(round(6 + 24 / 60 + 6 / 3600, 4));

    $b = $events[1];
    expect($b->type)->toBe(EclipseType::PARTIAL);
    expect($b->magnitudes->primary)->toBe(0.9299);
    expect($b->magnitudes->secondary)->toBe(1.9646);
    // partial lunar: total phase contacts are `-` (null)
    expect($b->contacts->penumbralStart->format('H:i:s'))->toBe('01:23:55');
    expect($b->contacts->partialStart->format('H:i:s'))->toBe('02:33:50');
    expect($b->contacts->totalStart)->toBeNull();
    expect($b->contacts->totalEnd)->toBeNull();
    expect($b->contacts->partialEnd->format('H:i:s'))->toBe('05:52:00');
    expect($b->contacts->penumbralEnd->format('H:i:s'))->toBe('07:01:47');
    expect(round($b->location->latitude, 4))->toBe(round(-(9 + 18 / 60 + 3 / 3600), 4));
});

it('skips a malformed record without throwing', function () {
    $fixture = <<<TXT
total solar	NOT-A-DATE	  17:45:56.8	-132.445818 km	1.0395/1.0178/1.0806	saros 126/48	2461265.240240
	  15:34:30.7    16:58:06.9    18:33:58.2    19:57:55.9 dt=71.3
	 -25° 5'40"	  65° 9'55"	2 min 18.03 sec
TXT;

    $c = (new EclipseParser())->parse(eclipseLines($fixture), EclipseKind::SOLAR, EclipseScope::GLOBAL);
    expect($c->all())->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseParserTest.php`
Expected: FAIL — `EclipseParser` not found.

- [ ] **Step 3: Write the parser**

`src/Support/Eclipses/EclipseParser.php`:

```php
<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Eclipses;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseCollection;
use DivineaLabs\Swisseph\Data\EclipseContacts;
use DivineaLabs\Swisseph\Data\EclipseEventData;
use DivineaLabs\Swisseph\Data\EclipseLocation;
use DivineaLabs\Swisseph\Data\EclipseMagnitudes;
use DivineaLabs\Swisseph\Data\SarosSeries;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;

final class EclipseParser
{
    /**
     * Parse swetest eclipse output into a collection.
     *
     * Contract: never throws — malformed records are skipped.
     *
     * @param  array<int, string>  $lines  stdout lines, echo + geo header already stripped
     */
    public function parse(array $lines, EclipseKind $kind, EclipseScope $scope): EclipseCollection
    {
        $linesPerRecord = $this->linesPerRecord($kind, $scope);

        // Group into records, each starting at a known type-word line.
        $records = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $firstToken = $this->firstToken($line);

            if (EclipseType::isTypeToken($firstToken)) {
                if (is_array($current)) {
                    $records[] = $current;
                }
                $current = [$line];

                continue;
            }

            if (is_array($current)) {
                $current[] = $line;
            }
        }

        if (is_array($current)) {
            $records[] = $current;
        }

        $events = [];

        foreach ($records as $record) {
            if (count($record) < $linesPerRecord) {
                continue; // incomplete block — skip tolerantly
            }

            $event = $this->parseRecord($record, $kind, $scope);
            if ($event instanceof EclipseEventData) {
                $events[] = $event;
            }
        }

        return new EclipseCollection($events);
    }

    private function linesPerRecord(EclipseKind $kind, EclipseScope $scope): int
    {
        if ($kind === EclipseKind::SOLAR && $scope === EclipseScope::LOCAL) {
            return 2;
        }

        return 3; // solar-global, lunar-global
    }

    /**
     * @param  array<int, string>  $record
     */
    private function parseRecord(array $record, EclipseKind $kind, EclipseScope $scope): ?EclipseEventData
    {
        if ($kind === EclipseKind::SOLAR) {
            return $scope === EclipseScope::LOCAL
                ? $this->parseSolarLocal($record)
                : $this->parseSolarGlobal($record);
        }

        return $this->parseLunarGlobal($record);
    }

    /**
     * Solar global, 3 lines:
     * L1: <type> solar  date  maxTime  <km> km  m1/m2/m3  saros s/n  JD
     * L2: c1 c2 c3 c4 dt=<x>
     * L3: lon  lat  <n> min <s> sec
     *
     * @param  array<int, string>  $record
     */
    private function parseSolarGlobal(array $record): ?EclipseEventData
    {
        $l1 = $this->tokens($record[0]);
        $l2 = $this->tokens($record[1]);
        $l3 = $this->tokens($record[2]);

        // l1: [type, 'solar', date, time, km, 'km', m1/m2/m3, 'saros', s/n, JD]
        $type = EclipseType::fromToken($l1[0] ?? '');
        if ($type === null) {
            return null;
        }

        $maxAt = $this->dateTime($l1[2] ?? '', $l1[3] ?? '');
        if ($maxAt === null) {
            return null;
        }

        $coreShadowKm = $this->floatOrNull($l1[4] ?? null);
        $magnitudes = $this->magnitudes($l1[6] ?? '');
        $saros = $this->saros($l1[8] ?? '');
        $julianDay = (float) ($l1[9] ?? 0.0);

        $date = $l1[2];
        $contacts = new EclipseContacts(
            penumbralStart: null,
            partialStart: $this->dateTime($date, $l2[0] ?? '-'),
            totalStart: $this->dateTime($date, $l2[1] ?? '-'),
            totalEnd: $this->dateTime($date, $l2[2] ?? '-'),
            partialEnd: $this->dateTime($date, $l2[3] ?? '-'),
            penumbralEnd: null,
        );
        $deltaT = $this->deltaT($record[1]);

        $location = $this->location($l3, 0);
        $duration = $this->duration($record[2]);

        return new EclipseEventData(
            kind: EclipseKind::SOLAR,
            type: $type,
            scope: EclipseScope::GLOBAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT,
            magnitudes: $magnitudes,
            saros: $saros,
            contacts: $contacts,
            location: $location,
            coreShadowKm: $coreShadowKm,
            duration: $duration,
        );
    }

    /**
     * Solar local, 2 lines:
     * L1: <type>  date  maxTime  m1/m2/m3  saros s/n  JD   (no km)
     * L2: <n> min <s> sec  c1 c2 c3 c4  dt=<x>   (`-` where not visible)
     *
     * @param  array<int, string>  $record
     */
    private function parseSolarLocal(array $record): ?EclipseEventData
    {
        $l1 = $this->tokens($record[0]);

        $type = EclipseType::fromToken($l1[0] ?? '');
        if ($type === null) {
            return null;
        }

        $date = $l1[1] ?? '';
        $maxAt = $this->dateTime($date, $l1[2] ?? '');
        if ($maxAt === null) {
            return null;
        }

        $magnitudes = $this->magnitudes($l1[3] ?? '');
        $saros = $this->saros($l1[5] ?? '');
        $julianDay = (float) ($l1[6] ?? 0.0);

        // L2 begins with "N min S.s sec" (3 tokens) then 4 contacts then dt=
        $l2 = $this->tokens($record[1]);
        $duration = $this->duration($record[1]);

        // Drop the duration tokens ("N", "min", "S.s", "sec") to find contacts.
        $contactTokens = $this->stripDurationTokens($l2);

        $contacts = new EclipseContacts(
            penumbralStart: null,
            partialStart: $this->dateTime($date, $contactTokens[0] ?? '-'),
            totalStart: $this->dateTime($date, $contactTokens[1] ?? '-'),
            totalEnd: $this->dateTime($date, $contactTokens[2] ?? '-'),
            partialEnd: $this->dateTime($date, $contactTokens[3] ?? '-'),
            penumbralEnd: null,
        );
        $deltaT = $this->deltaT($record[1]);

        return new EclipseEventData(
            kind: EclipseKind::SOLAR,
            type: $type,
            scope: EclipseScope::LOCAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT,
            magnitudes: $magnitudes,
            saros: $saros,
            contacts: $contacts,
            location: null,
            coreShadowKm: null,
            duration: $duration,
        );
    }

    /**
     * Lunar global, 3 lines:
     * L1: <type> lunar eclipse  date  maxTime  umbral/penumbral  saros s/n  JD
     * L2: c1 c2 c3 c4 c5 c6 dt=<x>   (`-` where absent)
     * L3: lon  lat
     *
     * @param  array<int, string>  $record
     */
    private function parseLunarGlobal(array $record): ?EclipseEventData
    {
        $l1 = $this->tokens($record[0]);

        // l1: [type, 'lunar', 'eclipse', date, time, mag/mag, 'saros', s/n, JD]
        $type = EclipseType::fromToken($l1[0] ?? '');
        if ($type === null) {
            return null;
        }

        $date = $l1[3] ?? '';
        $maxAt = $this->dateTime($date, $l1[4] ?? '');
        if ($maxAt === null) {
            return null;
        }

        $magnitudes = $this->magnitudes($l1[5] ?? '');
        $saros = $this->saros($l1[7] ?? '');
        $julianDay = (float) ($l1[8] ?? 0.0);

        $l2 = $this->tokens($record[1]);
        $contacts = new EclipseContacts(
            penumbralStart: $this->dateTime($date, $l2[0] ?? '-'),
            partialStart: $this->dateTime($date, $l2[1] ?? '-'),
            totalStart: $this->dateTime($date, $l2[2] ?? '-'),
            totalEnd: $this->dateTime($date, $l2[3] ?? '-'),
            partialEnd: $this->dateTime($date, $l2[4] ?? '-'),
            penumbralEnd: $this->dateTime($date, $l2[5] ?? '-'),
        );
        $deltaT = $this->deltaT($record[1]);

        $location = $this->location($this->tokens($record[2]), 0);

        return new EclipseEventData(
            kind: EclipseKind::LUNAR,
            type: $type,
            scope: EclipseScope::GLOBAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT,
            magnitudes: $magnitudes,
            saros: $saros,
            contacts: $contacts,
            location: $location,
            coreShadowKm: null,
            duration: null,
        );
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $line): array
    {
        $parts = preg_split('/\s+/', trim($line)) ?: [];

        return array_values(array_filter($parts, static fn ($t) => $t !== ''));
    }

    private function firstToken(string $line): string
    {
        return $this->tokens($line)[0] ?? '';
    }

    /**
     * Build a UTC Carbon from a d.m.Y date and HH:MM:SS[.f] time.
     * `-` (or any non-time token) → null.
     */
    private function dateTime(string $date, string $time): ?Carbon
    {
        $time = trim($time);
        $date = trim($date);

        if ($time === '' || $time === '-') {
            return null;
        }
        if (! preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $date)) {
            return null;
        }
        if (! preg_match('/^\d{1,2}:\d{2}:\d{2}(\.\d+)?$/', $time)) {
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

    /**
     * Split `m1/m2/m3` (solar) or `m1/m2` (lunar) into magnitudes.
     */
    private function magnitudes(string $token): EclipseMagnitudes
    {
        $parts = explode('/', trim($token));

        return new EclipseMagnitudes(
            primary: (float) ($parts[0] ?? 0.0),
            secondary: (float) ($parts[1] ?? 0.0),
            tertiary: isset($parts[2]) ? (float) $parts[2] : null,
        );
    }

    /**
     * Parse the `s/n` token following the literal `saros` (e.g. `121/61`).
     */
    private function saros(string $token): SarosSeries
    {
        $parts = explode('/', trim($token));

        return new SarosSeries(
            series: (int) ($parts[0] ?? 0),
            member: (int) ($parts[1] ?? 0),
        );
    }

    /**
     * Extract `dt=<x>` from a raw line.
     */
    private function deltaT(string $line): float
    {
        if (preg_match('/dt=([0-9.]+)/', $line, $m) === 1) {
            return (float) $m[1];
        }

        return 0.0;
    }

    /**
     * Parse a `<n> min <s> sec` substring → total seconds.
     */
    private function duration(string $line): ?float
    {
        if (preg_match('/(\d+)\s*min\s*([0-9.]+)\s*sec/', $line, $m) === 1) {
            return ((int) $m[1]) * 60 + (float) $m[2];
        }

        return null;
    }

    /**
     * Convert two degree tokens starting at $offset (`<deg>°<min>'<sec>"`) → EclipseLocation.
     * Because tokenizing on \s+ may split inside a coordinate (e.g. `87° 4' 6"`),
     * this works on the raw degree substrings re-joined from the remaining tokens.
     *
     * @param  array<int, string>  $tokens
     */
    private function location(array $tokens, int $offset): ?EclipseLocation
    {
        // Re-join everything from $offset and pull the first two coordinate groups.
        $rest = implode(' ', array_slice($tokens, $offset));

        // A coordinate group: optional sign, degrees, °, minutes, ', seconds, "
        $pattern = '/(-?\s*\d+)\s*°\s*(\d+)\s*\'\s*(\d+)\s*"/';
        if (preg_match_all($pattern, $rest, $matches, PREG_SET_ORDER) < 2) {
            return null;
        }

        $lon = $this->dms($matches[0]);
        $lat = $this->dms($matches[1]);

        return new EclipseLocation(longitude: $lon, latitude: $lat);
    }

    /**
     * @param  array<int, string>  $m  [full, deg(with optional sign), min, sec]
     */
    private function dms(array $m): float
    {
        $degToken = str_replace(' ', '', $m[1]);
        $negative = str_starts_with($degToken, '-');
        $deg = abs((int) $degToken);
        $min = (int) $m[2];
        $sec = (int) $m[3];

        $value = $deg + $min / 60 + $sec / 3600;

        return $negative ? -$value : $value;
    }

    /**
     * Drop the leading `N min S.s sec` tokens from a local L2 token list,
     * leaving the contact tokens.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function stripDurationTokens(array $tokens): array
    {
        // Find the 'sec' token; contacts start right after it.
        foreach ($tokens as $i => $t) {
            if ($t === 'sec') {
                return array_values(array_slice($tokens, $i + 1));
            }
        }

        return $tokens;
    }

    private function floatOrNull(?string $token): ?float
    {
        if ($token === null) {
            return null;
        }
        $token = trim($token);
        if ($token === '' || $token === '-') {
            return null;
        }
        if (! is_numeric($token)) {
            return null;
        }

        return (float) $token;
    }
}
```

> Parser note on coordinate tokenization: because swetest pads coordinates with spaces (`87° 4' 6"`), tokenizing on `\s+` can split a single coordinate across tokens. The `location()` helper therefore re-joins the L3 tokens and applies a `°…'…"` regex (`preg_match_all`) to recover both coordinate groups robustly, regardless of internal spacing. The dt= and duration helpers operate on the raw line (not tokens) for the same reason.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseParserTest.php`
Expected: PASS (all fixtures: solar global ×2, solar local ×2 incl. `-`, lunar global ×2 incl. partial `-`, empty, malformed-skip).

- [ ] **Step 5: Run the builder test again (now that EclipseParser exists)**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipsesBuilderTest.php`
Expected: PASS — the builder class now type-resolves `EclipseParser` cleanly.

- [ ] **Step 6: Commit**

```bash
git add src/Support/Eclipses/EclipseParser.php tests/Features/Support/Eclipses/EclipseParserTest.php
git commit -m "feat(eclipses): add block-aware EclipseParser against real swetest output"
```

---

### Task 9: Wire the factory, facade, and DI provider

**Files:**
- Modify: `src/Swisseph.php`
- Modify: `src/Facades/Swisseph.php`
- Modify: `src/SwissephServiceProvider.php`
- Test: `tests/Features/Support/Eclipses/EclipsesFactoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder;

it('exposes a fresh eclipses() sub-builder', function () {
    expect(Swisseph::eclipses())->toBeInstanceOf(EclipsesBuilder::class);
});

it('returns an isolated eclipses builder per call', function () {
    $a = Swisseph::eclipses()->solar()->count(9);
    $b = Swisseph::eclipses();

    // $b is fresh — building it without a kind throws, proving no shared state from $a
    expect(fn () => $b->build())
        ->toThrow(\DivineaLabs\Swisseph\Exceptions\EclipseKindNotSetException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipsesFactoryTest.php`
Expected: FAIL — `eclipses()` not defined on `Swisseph`.

- [ ] **Step 3: Add `eclipses()` to the Swisseph factory**

In `src/Swisseph.php`, add the import and method (alongside the SP0 `positions()`/`risings()`):

```php
use DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder;
```

```php
    public function eclipses(): EclipsesBuilder
    {
        return app(EclipsesBuilder::class);
    }
```

- [ ] **Step 4: Add the facade `@method` annotation**

In `src/Facades/Swisseph.php`, add to the docblock alongside the SP0 entries:

```php
 * @method static \DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder eclipses()
```

- [ ] **Step 5: Register the bindings**

In `src/SwissephServiceProvider.php` `registeringPackage()`, add alongside the SP0 binds:

```php
        $this->app->bind(\DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder::class);
        $this->app->bind(\DivineaLabs\Swisseph\Support\Eclipses\EclipseParser::class);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipsesFactoryTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add src/Swisseph.php src/Facades/Swisseph.php src/SwissephServiceProvider.php tests/Features/Support/Eclipses/EclipsesFactoryTest.php
git commit -m "feat(eclipses): wire eclipses() into factory, facade, and provider"
```

---

### Task 10: Integration test (self-skips without the binary)

**Files:**
- Create: `tests/Features/Support/Eclipses/EclipseIntegrationTest.php`

Mirrors the astro-core/rise integration pattern: resolves the configured executable, skips when absent, otherwise runs a real `solar()->global()` query and asserts a non-empty, well-typed result.

- [ ] **Step 1: Write the integration test**

```php
<?php

use DivineaLabs\Swisseph\Data\EclipseEventData;
use DivineaLabs\Swisseph\Facades\Swisseph;

function swetestBinaryAvailable(): bool
{
    $exe = (string) config('swisseph.executable', '');

    return $exe !== '' && is_file($exe) && is_executable($exe);
}

it('computes real solar global eclipses end-to-end', function () {
    if (! swetestBinaryAvailable()) {
        $this->markTestSkipped('swetest binary not available');
    }

    $collection = Swisseph::eclipses()
        ->solar()
        ->global()
        ->from('2026-01-01')
        ->count(2)
        ->get();

    expect($collection->all())->not->toBeEmpty();

    $first = $collection->first();
    expect($first)->toBeInstanceOf(EclipseEventData::class);
    expect($first->isSolar())->toBeTrue();
    expect($first->saros->series)->toBeGreaterThan(0);
    expect($first->magnitudes->primary)->toBeGreaterThan(0.0);
});

it('computes real lunar global eclipses end-to-end', function () {
    if (! swetestBinaryAvailable()) {
        $this->markTestSkipped('swetest binary not available');
    }

    $collection = Swisseph::eclipses()
        ->lunar()
        ->global()
        ->from('2026-01-01')
        ->count(2)
        ->get();

    expect($collection->all())->not->toBeEmpty();
    expect($collection->first()->isLunar())->toBeTrue();
});
```

- [ ] **Step 2: Run the integration test**

Run: `vendor/bin/pest tests/Features/Support/Eclipses/EclipseIntegrationTest.php`
Expected: PASS or SKIPPED (skipped when no binary is configured/installed).

- [ ] **Step 3: Commit**

```bash
git add tests/Features/Support/Eclipses/EclipseIntegrationTest.php
git commit -m "test(eclipses): add self-skipping end-to-end integration test"
```

---

### Task 11: Full suite + static analysis green gate

- [ ] **Step 1: Run the whole suite**

Run: `vendor/bin/pest`
Expected: PASS — all SP0 + SP1 tests green (eclipse integration may report SKIPPED).

- [ ] **Step 2: Run static analysis**

Run: `vendor/bin/phpstan analyse`
Expected: 0 errors. Fix any type issues the new classes surface (notably the `?EclipseParser`/`?SwissephExecutor` nullable-then-`app()` resolution and the `array<int,string>` token annotations).

- [ ] **Step 3: Commit any fixes**

```bash
git add -A
git commit -m "chore(eclipses): satisfy phpstan for the eclipses pipeline"
```

---

## Self-Review

**Spec coverage (§4 of the design):**
- §4.2 Builder API: `solar()`/`lunar()` (kind, throws `EclipseKindNotSetException` at `build()`), `global()`/`local(float,float,float=0.0)` (floats, NOT GeoLocation), `from()` → `b<d.m.Y>`, `count()` → `n<count>` (default 1), `backward()` → `bwd`, all seven type filters with the swetest flags (`total`/`partial`/`annular`/`anntot`/`penumbral`/`central`/`noncentral`), invalid kind×filter → `InvalidEclipseFilterException` at `build()`. Local emits `local` + `geopos<lon>,<lat>,<elev>`. eph-options propagate via the trait. ✅ (Task 7)
- §4.1/§4.4 Parser: block-aware grouping (3/2/3 lines), record-start detection on known type words, branch on kind+scope, tokenize on `\s+`, `-` → null, helpers for date/time, `m1/m2/m3`, `saros`, `dt=`, `°'"` DMS, `min/sec` duration. No events → empty; malformed → skip. Tests use the EXACT captured fixtures incl. `-` placeholders (solar local + partial lunar). ✅ (Task 8)
- §4.3 DTO: `EclipseEventData` (final readonly via spatie `Data`) with `kind`/`type`/`scope`/`maxAt`(Carbon UTC)/`julianDay`/`deltaT`/`magnitudes`/`saros`/`contacts`/`location`/`coreShadowKm`/`duration` + `isSolar()/isLunar()/isTotal()/isLocal()`. Value objects `EclipseMagnitudes`(primary/secondary/?tertiary), `SarosSeries`(series/member:int), `EclipseContacts`(6 ?Carbon, solar maps 4 onto partial/total slots), `EclipseLocation`(lon/lat). `EclipseCollection` with `all()/first()/solarOnly()/lunarOnly()`. ✅ (Tasks 3–6)
- Enums `EclipseKind`/`EclipseType`/`EclipseScope` string-backed; `EclipseType::fromToken()` maps `anntot`→HYBRID. ✅ (Task 1)
- Exceptions `EclipseKindNotSetException`/`InvalidEclipseFilterException` mirror the `Invalid…Exception` style (extend `InvalidArgumentException`, static factories). ✅ (Task 2)
- Factory/facade/provider wiring + fresh-per-call builder. ✅ (Task 9)
- Tests: BuilderTest (args for solar/lunar × global/local × every filter × backward × from/count × invalid-combo throws), ParserTest (exact fixtures, field-by-field incl. nullable contacts/magnitudes/saros/location/duration/deltaT), self-skipping integration. ✅ (Tasks 7, 8, 10)

**Type consistency:**
- `maxAt` and all contacts are `Carbon` (UTC), matching `RiseSetEvent`/`RiseParser` (not `CarbonImmutable`). ✅
- Geo is floats end-to-end (`local(float,float,float)`, `EclipseLocation` floats), no `GeoLocation`. ✅
- `magnitudes->tertiary` is `?float` (null for lunar's 2-value form). ✅
- `coreShadowKm` null for solar-local and lunar; `duration` null for lunar; `location` null for solar-local — all enforced in the respective parser branches and asserted in tests. ✅
- Builder returns `SwissephCommand` from `build()` and `EclipseCollection` from `get()`; `get()` declares `skipPrefixes` `['./', 'geo. long']` and lazy-resolves executor+parser via `app()` like `PositionsBuilder`. ✅

**Cross-task ordering caveat:** `EclipsesBuilder` (Task 7) references `EclipseParser` (Task 8) in its constructor/`get()`. The BuilderTest only exercises `build()` (no parser path), so Task 7 passes independently, but implement Task 8 before Task 11's full-suite/phpstan gate so the class graph resolves. ✅
