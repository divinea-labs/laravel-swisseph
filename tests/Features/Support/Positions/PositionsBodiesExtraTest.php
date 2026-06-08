<?php

use DivineaLabs\Swisseph\Data\PlanetBodyData;
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Enums\ComputedValue;
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;
use DivineaLabs\Swisseph\Enums\Sidereal;
use DivineaLabs\Swisseph\Exceptions\InvalidPlanetBodySelectionException;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;
use DivineaLabs\Swisseph\Support\Positions\PositionsParser;

it('allows a null index for catalog/computed bodies', function () {
    $data = PlanetBodyData::from([
        'index' => null,
        'name' => 'Sirius,alCMa',
    ]);

    expect($data->index)->toBeNull();
    expect($data->name)->toBe('Sirius,alCMa');
});

it('still accepts an integer index for numbered planets', function () {
    $data = PlanetBodyData::from([
        'index' => 0,
        'name' => 'Sun',
    ]);

    expect($data->index)->toBe(0);
    expect($data->name)->toBe('Sun');
});

it('exposes fixed-star / asteroid / moon body selectors', function () {
    expect(PlanetBodySelection::FIXED_STAR->value)->toBe('f');
    expect(PlanetBodySelection::ASTEROID->value)->toBe('s');
    expect(PlanetBodySelection::PLANETARY_MOON->value)->toBe('v');
});

it('maps each computed value to its swetest letter and label', function () {
    expect(ComputedValue::SIDEREAL_TIME->value)->toBe('x');
    expect(ComputedValue::DELTA_T->value)->toBe('q');
    expect(ComputedValue::ECLIPTIC_OBLIQUITY->value)->toBe('o');
    expect(ComputedValue::AYANAMSHA->value)->toBe('b');
    expect(ComputedValue::TIME_EQUATION->value)->toBe('y');

    expect(ComputedValue::SIDEREAL_TIME->getLabel())->toBe('Sidereal Time');
    expect(ComputedValue::DELTA_T->getLabel())->toBe('Delta T');
    expect(ComputedValue::ECLIPTIC_OBLIQUITY->getLabel())->toBe('Ecl. Obl.');
    expect(ComputedValue::AYANAMSHA->getLabel())->toBe('Ayanamsha');
    expect(ComputedValue::TIME_EQUATION->getLabel())->toBe('Time equation');
});

it('reports whether a computed value carries a time string or a float', function () {
    expect(ComputedValue::SIDEREAL_TIME->isTimeString())->toBeTrue();
    expect(ComputedValue::DELTA_T->isTimeString())->toBeFalse();
    expect(ComputedValue::AYANAMSHA->isTimeString())->toBeFalse();
});

it('matches a swetest label back to its computed value', function () {
    expect(ComputedValue::fromLabel('Sidereal Time'))->toBe(ComputedValue::SIDEREAL_TIME);
    expect(ComputedValue::fromLabel('Delta T'))->toBe(ComputedValue::DELTA_T);
    expect(ComputedValue::fromLabel('Ecl. Obl.'))->toBe(ComputedValue::ECLIPTIC_OBLIQUITY);
    expect(ComputedValue::fromLabel('Ayanamsha'))->toBe(ComputedValue::AYANAMSHA);
    expect(ComputedValue::fromLabel('Mercury'))->toBeNull();
});

it('builds a fixed-star selector with -pf and -xf<name>', function () {
    $builder = new PositionsBuilder;
    $builder->selectFixedStar('Sirius');

    $cli = $builder->build()->toCliString();

    expect($cli)->toContain('-pf');
    expect($cli)->toContain('-xfSirius');
});

it('trims the fixed-star name', function () {
    $builder = new PositionsBuilder;
    $builder->selectFixedStar('  Aldebaran  ');

    expect($builder->build()->toCliString())->toContain('-xfAldebaran');
});

it('rejects an empty fixed-star name', function () {
    $builder = new PositionsBuilder;

    expect(fn () => $builder->selectFixedStar('   '))
        ->toThrow(InvalidPlanetBodySelectionException::class);
});

it('builds an asteroid selector with -ps and -xs<number>', function () {
    $builder = new PositionsBuilder;
    $builder->selectAsteroid(433);

    $cli = $builder->build()->toCliString();

    expect($cli)->toContain('-ps');
    expect($cli)->toContain('-xs433');
});

it('rejects a non-positive MPC number', function () {
    $builder = new PositionsBuilder;

    expect(fn () => $builder->selectAsteroid(0))
        ->toThrow(InvalidPlanetBodySelectionException::class);
});

it('builds a computed-value selector onto -p', function () {
    $builder = new PositionsBuilder;
    $builder->selectComputedValue(ComputedValue::DELTA_T);

    expect($builder->build()->toCliString())->toContain('-pq');
});

it('concatenates multiple computed values onto a single -p', function () {
    $builder = new PositionsBuilder;
    $builder->selectComputedValue(ComputedValue::DELTA_T, ComputedValue::ECLIPTIC_OBLIQUITY);

    expect($builder->build()->toCliString())->toContain('-pqo');
});

it('emits ayanamsha together with the sidereal mode', function () {
    $builder = new PositionsBuilder;
    $builder
        ->selectComputedValue(ComputedValue::AYANAMSHA)
        ->withSidereal(Sidereal::LAHIRI);

    $cli = $builder->build()->toCliString();

    expect($cli)->toContain('-pb');
    expect($cli)->toContain('-sid1');
});

it('builds a planetary-moon selector with -pv and -xv<number>', function () {
    $builder = new PositionsBuilder;
    $builder->selectMoon(1);

    $cli = $builder->build()->toCliString();

    expect($cli)->toContain('-pv');
    expect($cli)->toContain('-xv1');
});

it('rejects a non-positive moon number', function () {
    $builder = new PositionsBuilder;

    expect(fn () => $builder->selectMoon(0))
        ->toThrow(InvalidPlanetBodySelectionException::class);
});

it('parses a fixed-star row with a comma-joined catalog name and null index', function () {
    // Fixture was captured with -fPLBl (name, lon-deg, lat-deg, lon-dec).
    // The builder here uses defaults [PLANET_INDEX, PLANET_NAME, LONGITUDE_DECIMAL,
    // SPEED_LONGITUDE_DECIMAL] + LATITUDE_DEGREE, giving value properties:
    // [LONGITUDE_DECIMAL, SPEED_LONGITUDE_DECIMAL, LATITUDE_DEGREE].
    // These are mapped positionally onto the fixture's 3 value columns.
    $builder = (new PositionsBuilder)
        ->selectFixedStar('Sirius')
        ->withProperties(AstroProperties::LATITUDE_DEGREE);

    $lines = fixtureLines('swetest-fixedstar-sirius.txt');

    $frame = (new PositionsParser)->parse($lines, $builder);

    expect($frame->planet_bodies)->toHaveCount(1);

    $star = $frame->planet_bodies[0];
    expect($star['planet_body']->index)->toBeNull();
    expect($star['planet_body']->name)->toBe('Sirius,alCMa');

    // Value properties map positionally to fixture columns:
    //   col 0 (104°26'56.6685) → LONGITUDE_DECIMAL
    //   col 1 (-39°36'39.8652) → SPEED_LONGITUDE_DECIMAL
    //   col 2 (104.4490746)    → LATITUDE_DEGREE
    $byKey = collect($star['properties'])->keyBy('property');
    expect($byKey['longitude_decimal']->value)->toBe("104°26'56.6685");
    expect($byKey['speed_longitude_decimal']->value)->toBe("-39°36'39.8652");
    expect($byKey['latitude_degree']->value)->toBe('104.4490746');
});

it('parses an asteroid row with its name and null index', function () {
    // Fixture was captured with -fPLl (name, lon-deg, lon-dec).
    // Builder defaults give value properties [LONGITUDE_DECIMAL, SPEED_LONGITUDE_DECIMAL].
    // Two fixture columns map to those two properties positionally.
    $builder = (new PositionsBuilder)->selectAsteroid(433);

    $lines = fixtureLines('swetest-asteroid-eros.txt');

    $frame = (new PositionsParser)->parse($lines, $builder);

    $eros = $frame->planet_bodies[0];
    expect($eros['planet_body']->index)->toBeNull();
    expect($eros['planet_body']->name)->toBe('Eros');

    // col 0 (31°31'56.5890) → LONGITUDE_DECIMAL
    // col 1 (31.5323858)    → SPEED_LONGITUDE_DECIMAL
    $byKey = collect($eros['properties'])->keyBy('property');
    expect($byKey['longitude_decimal']->value)->toBe("31°31'56.5890");
    expect($byKey['speed_longitude_decimal']->value)->toBe('31.5323858');
});

it('parses computed-value rows keeping the raw datum (no float coercion)', function () {
    $builder = (new PositionsBuilder)->selectComputedValue(
        ComputedValue::SIDEREAL_TIME,
        ComputedValue::DELTA_T,
        ComputedValue::ECLIPTIC_OBLIQUITY,
        ComputedValue::AYANAMSHA,
    );

    $lines = fixtureLines('swetest-computed-codes.txt');

    $frame = (new PositionsParser)->parse($lines, $builder);

    expect($frame->planet_bodies)->toHaveCount(4);

    $byName = collect($frame->planet_bodies)
        ->keyBy(fn ($b) => $b['planet_body']->name);

    // Names are the computed labels; indices are null.
    expect($byName->keys()->all())->toContain('Sidereal Time', 'Delta T', 'Ecl. Obl.', 'Ayanamsha');
    expect($byName['Sidereal Time']['planet_body']->index)->toBeNull();

    // Sidereal Time value is preserved as a TIME STRING, not coerced to a number.
    $siderealValue = $byName['Sidereal Time']['properties'][0]->value;
    expect($siderealValue)->toBe('01.01.2026 12:00:00 UT');
    expect($siderealValue)->toBeString();

    // Float-valued codes are still stored as their raw string token.
    expect($byName['Delta T']['properties'][0]->value)->toBe('71.0013120');
    expect($byName['Ecl. Obl.']['properties'][0]->value)->toBe('23.4381321');
    expect($byName['Ayanamsha']['properties'][0]->value)->toBe('24.2218573');
});

it('recognises a computed-value label via ComputedValue::fromLabel', function () {
    expect(ComputedValue::fromLabel('Sidereal Time'))->toBe(ComputedValue::SIDEREAL_TIME);
});

it('clears a prior fixed-star/asteroid target when selectBodies() is called afterwards', function () {
    $builder = new PositionsBuilder;
    $builder->selectFixedStar('Sirius')->selectBodies(PlanetBodySelection::SUN);

    $cli = $builder->build()->toCliString();

    expect($cli)->toContain('-p0');
    expect($cli)->not->toContain('-xfSirius');
});
