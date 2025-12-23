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
- Laravel **11.x** or **12.x**
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

## Usage

```php
$swisseph = Swisseph::setDateTime('2025-03-23 21:21:00', 'Europe/Warsaw')
    ->setLocation(17.038538, 51.107883, 'Wroclaw')
    ->selectBodies(PlanetBodySelection::SUN)
    ->withHouses(HouseSystems::KOCH)
    ->get();
```
> `setLocation()` expects coordinates as: **longitude, latitude**.

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
local, transparent, and under the user’s control.

## Credits

- **Divinea Labs** – project vision, architecture and long-term maintenance
- skyel – original author and core implementation
- [All Contributors](../../contributors)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
