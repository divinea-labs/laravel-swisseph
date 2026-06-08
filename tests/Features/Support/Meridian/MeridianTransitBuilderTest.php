<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\MeridianGeoPositionNotSetException;
use DivineaLabs\Swisseph\Support\Meridian\MeridianTransitBuilder;

it('builds a -metr command for the Sun at a geographic position', function () {
    $cli = (new MeridianTransitBuilder)
        ->at(21.0, 52.2, 100.0)
        ->from('2026-01-01')
        ->count(2)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-metr');
    expect($cli)->toContain('-p0');                 // default body = Sun
    expect($cli)->toContain('-geopos21,52.2,100');
    expect($cli)->toContain('-b01.01.2026');
    expect($cli)->toContain('-n2');
});

it('throws when no geographic position is set', function () {
    expect(fn () => (new MeridianTransitBuilder)->from('2026-01-01')->build())
        ->toThrow(MeridianGeoPositionNotSetException::class);
});

it('selects a different body', function () {
    $cli = (new MeridianTransitBuilder)
        ->forBody(PlanetBody::MOON)
        ->at(21.0, 52.2)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-p1'); // Moon
});

it('emits -bwd for a backward search', function () {
    $cli = (new MeridianTransitBuilder)
        ->at(21.0, 52.2)
        ->backward()
        ->build()
        ->toCliString();

    expect($cli)->toContain('-bwd');
});

it('defaults to a single step when count() is not called', function () {
    $cli = (new MeridianTransitBuilder)
        ->at(21.0, 52.2)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-n1');
});
