# Laravel Swisseph

[![Latest Version on Packagist](https://img.shields.io/packagist/v/divinea-labs/laravel-swisseph.svg?style=flat-square)](https://packagist.org/packages/divinea-labs/laravel-swisseph)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/divinea-labs/laravel-swisseph/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/divinea-labs/laravel-swisseph/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/divinea-labs/laravel-swisseph/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/divinea-labs/laravel-swisseph/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/divinea-labs/laravel-swisseph.svg?style=flat-square)](https://packagist.org/packages/divinea-labs/laravel-swisseph)

A clean, explicit Laravel wrapper for the **Swiss Ephemeris (`swetest`) CLI**.

Designed for projects that require **deterministic astronomical calculations**,
**full control over ephemeris configuration**, and **structured, stable data output**.

It focuses on:
- explicit configuration (no hidden magic)
- deterministic CLI generation
- stable DTO contracts
- strong typing via enums

## Why this package exists

Swiss Ephemeris is one of the most precise astronomical calculation engines available,
but its CLI interface (`swetest`) is difficult to integrate cleanly into modern Laravel applications.

This package exists to:

- provide a **fluent, explicit API** for building `swetest` commands
- eliminate stringly-typed CLI calls
- expose **stable DTO contracts** instead of raw text output
- keep all calculations **local, private, and reproducible**

## Requirements & Compatibility

- PHP **8.3** or **8.4**
- Laravel **11.x**, **12.x** or **13.x**
- Swiss Ephemeris (`swetest`) installed locally

This package is tested against the officially supported Laravel versions
using Orchestra Testbench and GitHub Actions.

## Installation

You can install the package via composer:

```bash
composer require divinea-labs/laravel-swisseph
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-swisseph-config"
```

This is the contents of the published config file:

```php
return [
    /*
   |--------------------------------------------------------------------------
   | Path to swetest executable
   |--------------------------------------------------------------------------
   */
    'executable' => env('SWISSEPH_EXECUTABLE', base_path('swisseph/swetests')),

    /*
    |--------------------------------------------------------------------------
    | Path to ephemeris directory
    |--------------------------------------------------------------------------
    */
    'ephemeris_dir' => env('SWISSEPH_EPHEMERIS_DIR', base_path('swisseph/ephe')),

    /*
    |--------------------------------------------------------------------------
    | Ephemeris computation options
    |
    | ALL VALUES COME FROM .env AND ARE MAPPED SAFELY TO ENUMS
    |
    | .env example:
    |   SWISSEPH_EPHEMERIS_TYPE=eswe
    |   SWISSEPH_TRUE_POSITIONS=true
    |   SWISSEPH_NO_NUTATION=nonut
    |
    | All allowed values are those of EphOptions::cases()
    |--------------------------------------------------------------------------
    */
    'eph_options' => array_values(array_filter([
        EphOptions::tryFrom(env('SWISSEPH_EPHEMERIS_TYPE', EphOptions::SWISS_TYPE->value))
        ?? EphOptions::SWISS_TYPE,

        EphOptions::tryFrom(env('SWISSEPH_TRUE_POSITIONS', EphOptions::TRUE_POSITIONS->value))
        ?? EphOptions::TRUE_POSITIONS,

        EphOptions::tryFrom(env('SWISSEPH_NO_NUTATION', EphOptions::NO_NUTATION->value))
        ?? EphOptions::NO_NUTATION,
    ])),

    /*
    |--------------------------------------------------------------------------
    | Default house system
    |
    | Example in .env:
    |   SWISSEPH_HOUSESYSTEM=P
    |--------------------------------------------------------------------------
    */
    'default_house_system' => HouseSystems::tryFrom(env('SWISSEPH_HOUSESYSTEM', 'P'))
        ?? HouseSystems::PLACIDUS,

    /*
     * |--------------------------------------------------------------------------
     * | Timeout for swetest execution (in seconds)
     * |--------------------------------------------------------------------------
     */
    'timeout' => env('SWISSEPH_TIMEOUT', 10),
];
```

## Pipelines

The package exposes seven calculation pipelines, each accessed via a dedicated sub-builder factory method:

| Method | Builder | Purpose |
|---|---|---|
| `Swisseph::positions()` | `PositionsBuilder` | Planetary positions, houses, properties, fixed stars, asteroids, planetary moons, computed values, batch & relative ephemeris |
| `Swisseph::risings()` | `RisingsBuilder` | Rise/set events for any body |
| `Swisseph::eclipses()` | `EclipsesBuilder` | Solar & lunar eclipses, global or local, with contacts, magnitudes and Saros data |
| `Swisseph::occultations()` | `OccultationsBuilder` | Planetary & stellar occultations by the Moon, global or local |
| `Swisseph::meridianTransits()` | `MeridianTransitBuilder` | Upper/lower meridian transits for any body |
| `Swisseph::orbitalElements()` | `OrbitalElementsBuilder` | Osculating orbital elements for any body |
| `Swisseph::heliacal()` | `HeliacalBuilder` | Heliacal risings/settings and first/last visibility |

Every factory call returns a **fresh, isolated builder** — no shared mutable state between calls.

---

## Usage

Laravel Swisseph is designed around **explicit, deterministic defaults** with **optional overrides**.

---

### Planetary positions

```php
use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Enums\HouseSystems;

// Zero-configuration: current time, default bodies
$result = Swisseph::positions()->get();

// With location and houses
$result = Swisseph::positions()
    ->setLocation(17.038538, 51.107883, 'Wroclaw')
    ->withHouses(HouseSystems::PLACIDUS)
    ->get();

// With explicit date/time and body selection
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;

$result = Swisseph::positions()
    ->setDateTime('2025-03-23 21:21:00', 'Europe/Warsaw')
    ->setLocation(17.038538, 51.107883, 'Wroclaw')
    ->selectBodies(PlanetBodySelection::SUN)
    ->withHouses(HouseSystems::KOCH)
    ->get();
```

> `setLocation()` expects coordinates in the order: **longitude, latitude**.

Available `positions()` builder options:

* `setDateTime(...)`
* `setLocation(...)`
* `setPlace(...)`
* `withEphOptions(...)`
* `selectBodies(...)`
* `withProperties(...)`
* `withHouses(...)`
* `setObserverPosition(...)`
* `withSidereal(...)`
* `withCustomSidereal(...)`

---

### Fixed stars, asteroids, planetary moons & computed values

Beyond the default planets, `positions()` can target catalog bodies and
output-only computed values. Each selector replaces the default body set:

```php
use DivineaLabs\Swisseph\Enums\ComputedValue;
use DivineaLabs\Swisseph\Enums\Sidereal;
use DivineaLabs\Swisseph\Facades\Swisseph;

// Fixed star by catalog name
$frame = Swisseph::positions()->selectFixedStar('Sirius')->get();

// Asteroid by Minor Planet Center number (433 = Eros)
$frame = Swisseph::positions()->selectAsteroid(433)->get();

// Planetary moon by swetest moon number
$frame = Swisseph::positions()->selectMoon(1)->get();

// Computed values (variadic) — sidereal time, ΔT, obliquity, ayanamsha, …
$frame = Swisseph::positions()
    ->selectComputedValue(
        ComputedValue::SIDEREAL_TIME,
        ComputedValue::DELTA_T,
        ComputedValue::AYANAMSHA,
    )
    ->withSidereal(Sidereal::LAHIRI) // required for AYANAMSHA
    ->get();
```

For catalog and computed bodies the parsed row's `index` is `null` and its
`name` carries the catalog/computed label. Invalid targets (empty star name,
non-positive MPC/moon number, empty computed-value list) throw
`InvalidPlanetBodySelectionException`.

---

### Batch ephemeris — N frames in a single process

`steps()` turns a single `positions()` query into a **time series**: swetest
emits *N* time-stepped frames from **one** process instead of one spawn per
moment. Fetch the result with `getSeries()`, which returns an `AstroTimeSeries`.

```php
use DivineaLabs\Swisseph\Facades\Swisseph;

$series = Swisseph::positions()
    ->setDateTime('2026-01-01 12:00:00', 'UTC')
    ->steps(5, '1') // 5 frames, 1-day step
    ->getSeries();

$series->count();              // 5
$series->first();              // first AstroTimeFrame
$series->last();               // last AstroTimeFrame
$series->at('02.01.2026 12:00:00'); // lookup by timestamp token

foreach ($series->frames as $frame) {
    $frame->date;              // Carbon timestamp for this frame
    $frame->planet_bodies;     // positions/properties for this frame
}
```

Each row is automatically prefixed with a timestamp column so frames can be
grouped deterministically. Step-size tokens are passed to swetest verbatim:
bare digits = days, `m` = minutes, `mo` = months, `y` = years, `s` = seconds.
A step count below `1` throws `InvalidStepCountException`.

---

### Relative ephemeris — differential & midpoint

`differentialTo()` prints the difference between each selected body and a
reference; `midpointTo()` prints their midpoint. The reference is a
`PlanetBodySelection` (swetest selection code), and the two are mutually
exclusive (last call wins).

```php
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;
use DivineaLabs\Swisseph\Facades\Swisseph;

// Mercury relative to the Sun → row name "Mer-Sun"
$frame = Swisseph::positions()
    ->selectBodies(PlanetBodySelection::MERCURY)
    ->differentialTo(PlanetBodySelection::SUN)
    ->get();

// Saturn/Chiron midpoint → row name "Sat/Chi"
$frame = Swisseph::positions()
    ->selectBodies(PlanetBodySelection::SATURN)
    ->midpointTo(PlanetBodySelection::CHIRON)
    ->get();
```

---

### Inspecting the generated CLI command

```php
$command = Swisseph::positions()->getCliCommand();
```

---

## Rise / Set events

Laravel Swisseph can compute **rise and set times** for celestial bodies using
the Swiss Ephemeris `-rise` pipeline via `Swisseph::risings()`.

Results are returned as structured DTOs with deterministic timezone behavior.

### Sunrise / sunset (UTC day — Mode A)

Mode A treats the date as a **UTC calendar day**.

```php
use DivineaLabs\Swisseph\Facades\Swisseph;

$r = Swisseph::risings()
    ->setDateTime('2026-02-14')
    ->setLocation(17.038538, 51.107883)
    ->getSunEvents();

$r->rise()->utcAt;
$r->set()->utcAt;
$r->dayLength();
```

Example:

```
rise: 2026-02-14T06:10:32.900Z
set:  2026-02-14T16:02:08.300Z
```

In Mode A:

- timestamps are UTC
- `localAt` and `localDate` are null
- filtering is done by UTC day

---

### Local calendar day (timezone — Mode B)

Mode B filters events by **local calendar day**.

```php
$r = Swisseph::risings()
    ->setDateTime('2026-02-14', 'Europe/Warsaw')
    ->setLocation(17.038538, 51.107883)
    ->getSunEvents();

$r->rise()->localAt;
$r->set()->localAt;
```

Example:

```
rise: 2026-02-14T07:10:32.900+01:00
set:  2026-02-14T17:02:08.300+01:00
```

In Mode B:

- UTC timestamps are preserved internally
- `localAt` contains projected local time
- filtering is done by local calendar day

---

### Any celestial body

```php
use DivineaLabs\Swisseph\Enums\PlanetBody;

$r = Swisseph::risings()
    ->setDateTime('2026-02-14')
    ->setLocation(17.038538, 51.107883)
    ->getRiseSetEvents(PlanetBody::SATURN);
```

---

### Multiple bodies (batch orchestration)

Swiss Ephemeris supports only **one body per CLI run**.  
The wrapper orchestrates multiple runs automatically.

```php
use DivineaLabs\Swisseph\Enums\PlanetBody;

$batch = Swisseph::risings()
    ->setDateTime('2026-02-14')
    ->setLocation(17.038538, 51.107883)
    ->getRiseSetEventsForBodies([
        PlanetBody::SUN,
        PlanetBody::MOON,
        PlanetBody::SATURN,
    ]);

$batch->forBody(PlanetBody::SUN)->rise();
$batch->forBody(PlanetBody::MOON)->rise();
$batch->forBody(PlanetBody::SATURN)->rise();
```

---

### Optional configuration

```php
use DivineaLabs\Swisseph\Enums\DiscMode;

$r = Swisseph::risings()
    ->setDateTime('2026-02-14', 'Europe/Warsaw')
    ->setLocation(17.038538, 51.107883)
    ->setDiscMode(DiscMode::BOTTOM)
    ->withoutRefraction()
    ->anchorToLocalMidnight()
    ->searchBackward()
    ->getSunEvents();
```

Available options include:

- disc mode (`BOTTOM`, `CENTER`, `HINDU`)
- atmospheric refraction toggle
- backward search
- atmospheric model
- observer model
- optical model

All options are explicit and deterministic.

---

## Eclipses

Compute **solar and lunar eclipses** via `Swisseph::eclipses()`. Choose the kind
(`solar()` / `lunar()`) and scope (`global()` default, or `local(lon, lat, elev)`),
then read structured DTOs with contact times, magnitudes and Saros data.

```php
use DivineaLabs\Swisseph\Facades\Swisseph;

// Next two global solar eclipses
$collection = Swisseph::eclipses()
    ->solar()
    ->from('2026-01-01')
    ->count(2)
    ->get();

foreach ($collection->all() as $eclipse) {
    $eclipse->type;                 // EclipseType (TOTAL, ANNULAR, PARTIAL, …)
    $eclipse->maxAt;                // Carbon — moment of maximum (UTC)
    $eclipse->magnitudes->primary;  // eclipse magnitude
    $eclipse->saros->series;        // Saros series number
    $eclipse->contacts->partialStart; // ?Carbon contact time
}

// Local total lunar eclipse for an observer
$local = Swisseph::eclipses()
    ->lunar()
    ->local(17.038538, 51.107883)
    ->onlyTotal()
    ->get();
```

Filters (`onlyTotal()`, `onlyPartial()`, `onlyAnnular()`, `onlyPenumbral()`,
`onlyCentral()`, …), `from()`, `count()` and `backward()` mirror the swetest
`-eclipse` options. Calling `get()` without `solar()`/`lunar()` throws
`EclipseKindNotSetException`; `lunar()->local()` is unsupported and throws
`InvalidEclipseFilterException`.

---

## Occultations

Compute **occultations of planets and fixed stars by the Moon** via
`Swisseph::occultations()`. Pick a target with `forBody()` or `forStar()`,
optionally restrict to a local observer.

```php
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Facades\Swisseph;

// Global occultations of Jupiter
$collection = Swisseph::occultations()
    ->forBody(PlanetBody::JUPITER)
    ->from('2026-01-01')
    ->count(5)
    ->get();

foreach ($collection->all() as $event) {
    $event->type;                    // OccultationType (TOTAL, ANNULAR, PARTIAL)
    $event->maxAt;                   // Carbon — peak (UTC)
    $event->magnitude;               // float
    $event->contacts->exteriorStart; // ?Carbon
}

// Local occultation of a fixed star
$local = Swisseph::occultations()
    ->forStar('Aldebaran')
    ->local(17.038538, 51.107883)
    ->get();
```

Calling `get()` without a target throws `OccultationTargetNotSetException`.

---

## Meridian transits

Compute **upper and lower meridian transits** for any body via
`Swisseph::meridianTransits()`. A geographic position is required.

```php
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Facades\Swisseph;

$transits = Swisseph::meridianTransits()
    ->forBody(PlanetBody::VENUS)
    ->at(17.038538, 51.107883)
    ->from('2026-01-01')
    ->count(2)
    ->get();

foreach ($transits->all() as $transit) {
    $transit->date;            // 'Y-m-d' (UTC) of the upper transit
    $transit->upperTransitAt;  // Carbon
    $transit->lowerTransitAt;  // Carbon
}
```

Calling `get()` without `at()` throws `MeridianGeoPositionNotSetException`.

---

## Orbital elements

Compute **osculating orbital elements** for any body via
`Swisseph::orbitalElements()`. Returns a single DTO.

```php
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Facades\Swisseph;

$elements = Swisseph::orbitalElements()
    ->forBody(PlanetBody::MARS)
    ->from('2026-01-01')
    ->get();

$elements->semiAxis;             // semi-major axis
$elements->eccentricity;         // eccentricity
$elements->inclination;          // inclination (J2000)
$elements->siderealPeriodYears;  // sidereal period
```

Calling `get()` without `forBody()` throws `OrbitalElementsBodyNotSetException`.

---

## Heliacal events

Compute **heliacal risings/settings and first/last visibility** via
`Swisseph::heliacal()`. Target a planet (`forBody()`) or fixed star
(`forStar()`); a geographic position is required. Atmospheric, observer and
optical models are optional overrides.

```php
use DivineaLabs\Swisseph\Enums\HeliacalEventType;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Facades\Swisseph;

$events = Swisseph::heliacal()
    ->forBody(PlanetBody::VENUS)
    ->at(17.038538, 51.107883)
    ->from('2026-01-01')
    ->get();

foreach ($events->all() as $event) {
    $event->type;             // HeliacalEventType
    $event->at;               // Carbon — event time (UT)
    $event->optimumAt;        // Carbon — optimum visibility
    $event->durationMinutes;  // float
}

// Filter by event type, with custom models
$risings = Swisseph::heliacal()
    ->forStar('Sirius')
    ->at(17.038538, 51.107883)
    ->withAtmosphere(1013.25, 15.0, 40.0, 0.0)
    ->withObserver(45.0, 1.2)
    ->get()
    ->ofType(HeliacalEventType::HELIACAL_RISING);
```

Calling `get()` without `at()` throws `HeliacalGeoPositionNotSetException`.

---

### Summary

* Each pipeline is accessed via its own sub-builder (`positions()`, `risings()`, `eclipses()`, `occultations()`, `meridianTransits()`, `orbitalElements()`, `heliacal()`)
* Each call returns a **fresh, isolated builder** — no shared mutable state
* Date/time defaults to the current moment (UTC)
* Observer location only matters when houses are enabled (positions pipeline)
* All configuration methods are optional overrides
* Defaults are explicit, deterministic, and transparent


## Documentation

- [Basic example (CLI + raw output + parsed DTO)](docs/swisseph-cli/example-default.md)
- [DTO contract](docs/dto.md)
- [Enums reference](docs/enums.md)
- [AstroProperties contract](docs/astro-properties.contract.md)
- [Environment variables](docs/env.md)

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## About Divinea Labs

This package is developed by **Divinea Labs**.

Divinea builds applications and technologies that support human development,
with a strong emphasis on personal freedom, privacy, and conscious interaction
with natural cycles.

Laravel Swisseph is part of a broader ecosystem of tools designed to remain
local, transparent, and under the user's control.

## Credits

- **Divinea Labs** – project vision, architecture and long-term maintenance
- skyel – original author and core implementation
- [All Contributors](../../contributors)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
