<?php

use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\HouseSystems;
use DivineaLabs\Swisseph\Enums\ObserverPosition;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;
use DivineaLabs\Swisseph\Enums\Sidereal;
use DivineaLabs\Swisseph\Exceptions\InvalidPlanetBodyForPlanetocentricCalculationException;
use DivineaLabs\Swisseph\Exceptions\InvalidPlanetBodySelectionException;
use DivineaLabs\Swisseph\Support\Command\SwissephCommandBuilder;
use Illuminate\Support\Carbon;

/*beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2025, 3, 23, 20, 21, 0, 'UTC'));
});*/

afterEach(function () {
    Carbon::setTestNow();
});

/*
 * DateTime and Timezone
 */
it('converts timezone to UTC and uses UT argument', function () {
    Carbon::setTestNow(Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC'));

    $builder = new SwissephCommandBuilder;
    $builder->setDateTime('2025-03-23 21:21:00', 'Europe/Warsaw');

    $cmd = $builder->build()->toProcessArray();

    expect($cmd)->toContain('-b23.03.2025');
    expect($cmd)->toContain('-ut20:21:00');

});

/*
 * Location and Houses
 */
it('builds house argument with lon,lat and system and formats floats', function () {
    $builder = new SwissephCommandBuilder;

    $builder
        ->setLocation(17.0385380, 51.1078830, 'Wroclaw')
        ->withHouses(HouseSystems::KOCH);

    $cmd = $builder->build()->toCliString();

    expect($cmd)->toContain('-house17.038538,51.107883,K');
});

it('formats negative coordinates without trailing zeros', function () {
    $builder = new SwissephCommandBuilder;

    $builder->setLocation(-0.0015450, 51.4779280)->withHouses(HouseSystems::PLACIDUS);

    $cmd = $builder->build()->toCliString();

    expect($cmd)->toContain('-house-0.001545,51.477928,P');
});

/*
 * Planet body selection
 */
it('accepts selectBodies as enum', function () {
    $builder = new SwissephCommandBuilder;
    $builder->selectBodies(PlanetBodySelection::SUN);

    expect($builder->build()->toCliString())->toContain('-p0');
});

it('accepts selectBodies as array of enums', function () {
    $builder = new SwissephCommandBuilder;
    $builder->selectBodies([PlanetBodySelection::SUN, PlanetBodySelection::MOON]);

    expect($builder->build()->toCliString())->toContain('-p01');
});

it('accepts selectBodies as string value', function () {
    $builder = new SwissephCommandBuilder;
    $builder->selectBodies('d');

    expect($builder->build()->toCliString())->toContain('-pd');
});

it('uses default bodies if none selected', function () {
    $builder = new SwissephCommandBuilder;

    expect($builder->build()->toCliString())->toContain('-pd');
});

it('throws on invalid selectBodies string', function () {
    $builder = new SwissephCommandBuilder;

    expect(fn () => $builder->selectBodies('xxx'))
        ->toThrow(InvalidPlanetBodySelectionException::class);
});

/*
 * Observer positions
 */

it('builds topocentric observer argument', function () {
    $builder = new SwissephCommandBuilder;
    $builder->setLocation(17.038538, 51.107883, elevation: 123.4);
    $builder->setObserverPosition(ObserverPosition::TOPOCENTRIC);

    expect($builder->build()->toCliString())
        ->toContain('-topo17.038538,51.107883,123.4');
});

it('throws if planetocentric observer is used without planet', function () {
    $builder = new SwissephCommandBuilder;
    $builder->setObserverPosition(ObserverPosition::PLANETOCENTRIC);

    expect(fn () => $builder->build())
        ->toThrow(InvalidPlanetBodyForPlanetocentricCalculationException::class);
});

it('builds planetocentric argument when planet provided', function () {
    $builder = new SwissephCommandBuilder;
    $builder->setObserverPosition(ObserverPosition::PLANETOCENTRIC, PlanetBody::MARS);

    expect($builder->build()->toCliString())->toContain('-pc4');
});

/**
 * Sidereal
 */
it('does not add sidereal argument by default', function () {
    $builder = new SwissephCommandBuilder;

    expect($builder->build()->toCliString())->not->toContain('-sid');
});

it('adds sidereal argument when requested', function () {
    $builder = new SwissephCommandBuilder;
    $builder->withSidereal(Sidereal::LAHIRI);

    expect($builder->build()->toCliString())->toContain('-sid1');
});

it('deduplicates eph options and keeps stable order', function () {
    $builder = new SwissephCommandBuilder;

    $builder->withEphOptions(
        EphOptions::TRUE_POSITIONS,
        [EphOptions::NO_NUTATION, EphOptions::TRUE_POSITIONS],
        EphOptions::SWISS_TYPE,
    );

    $cli = $builder->build()->toCliString();

    // should expect only once each, and in order sorted by value (alphabetical)
    expect($cli)->toMatch('/-eswe/');   // eswe
    expect($cli)->toMatch('/-nonut/');  // nonut
    expect($cli)->toMatch('/-true/');   // true

    expect(substr_count($cli, '-true'))->toBe(1);
});

/**
 * Stare:
 */
it('adds custom properties without overriding default ones', function () {
    $builder = new SwissephCommandBuilder;

    $builder->withProperties([
        AstroProperties::LATITUDE_DECIMAL,
        AstroProperties::LONGITUDE_DECIMAL,
    ]);

    $properties = $builder->getProperties();

    expect($properties)
        ->toContain(AstroProperties::LATITUDE_DECIMAL)
        ->toContain(AstroProperties::LONGITUDE_DECIMAL)
        ->toContain(AstroProperties::PLANET_INDEX)
        ->toContain(AstroProperties::PLANET_NAME);
});

it('does not duplicate custom properties', function () {
    $builder = new SwissephCommandBuilder;

    $builder->withProperties([AstroProperties::LATITUDE_DECIMAL]);
    $builder->withProperties([AstroProperties::LATITUDE_DECIMAL]);

    $properties = $builder->getProperties();

    expect($properties)
        ->toHaveLength(count(array_unique(array_map(fn ($p) => $p->value, $properties)))); // no dups
});

it('adds house properties and sets housesystem', function () {
    $builder = new SwissephCommandBuilder;

    $builder->withHouses(HouseSystems::PLACIDUS);

    $props = $builder->getProperties();

    expect($builder->getHouseSystem())->toBe(HouseSystems::PLACIDUS);

    expect($props)->toContain(AstroProperties::HOUSE_POSITION_DEGREES)
        ->toContain(AstroProperties::HOUSE_POSITION_DEGREES_DECIMAL)
        ->toContain(AstroProperties::HOUSE_NUMBER_DECIMAL);
});

it('allows any order of withProperties and withHouses without losing data', function () {
    $builder1 = new SwissephCommandBuilder;
    $builder1->withProperties(AstroProperties::LATITUDE_DECIMAL);
    $builder1->withHouses(HouseSystems::PLACIDUS);
    $props1 = $builder1->getProperties();

    $builder2 = new SwissephCommandBuilder;
    $builder2->withHouses(HouseSystems::PLACIDUS);
    $builder2->withProperties(AstroProperties::LATITUDE_DECIMAL);
    $props2 = $builder2->getProperties();

    expect(
        collect($props1)->pluck('value')->sort()->values()->all()
    )->toEqual(
        collect($props2)->pluck('value')->sort()->values()->all()
    );
});

it('does not carry properties between new builder instances', function () {
    $b1 = new SwissephCommandBuilder;
    $b1->withProperties(AstroProperties::LATITUDE_DECIMAL);

    $b2 = new SwissephCommandBuilder; // completely fresh

    expect($b2->getProperties())->not->toContain(AstroProperties::LATITUDE_DECIMAL);
});

it('builds minimal valid swetest command syntax', function () {
    Carbon::setTestNow('2025-03-23 20:21:00');

    $builder = new SwissephCommandBuilder;
    $array = $builder->build()->toProcessArray();

    expect($array)->toContain('-b23.03.2025');
    expect($array)->toContain('-ut20:21:00');

    expect(collect($array)->contains(fn ($arg) => str_starts_with($arg, '-p')))
        ->toBeTrue();

    expect(collect($array)->contains(fn ($arg) => str_starts_with($arg, '-f')))
        ->toBeTrue();

    expect($array)->toContain('-gPPP')
        ->toContain('-head');
});
