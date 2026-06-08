<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\PlanetBodySelection;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;
use DivineaLabs\Swisseph\Support\Positions\PositionsParser;

it('emits -d<code> for a differential ephemeris using the swetest selection code', function () {
    $cli = (new PositionsBuilder)
        ->selectBodies(PlanetBodySelection::MERCURY)
        ->differentialTo(PlanetBodySelection::SUN)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-p2');   // Mercury
    expect($cli)->toContain('-d0');   // differential vs Sun
    expect($cli)->not->toContain('-D'); // no midpoint flag
});

it('emits -D<code> for a midpoint ephemeris, using the letter code for non-planet bodies', function () {
    $cli = (new PositionsBuilder)
        ->selectBodies(PlanetBodySelection::SATURN)
        ->midpointTo(PlanetBodySelection::CHIRON)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-p6');   // Saturn
    expect($cli)->toContain('-DD');   // midpoint vs Chiron (code 'D', NOT internal number 15)
});

it('keeps differential and midpoint mutually exclusive — last call wins (midpoint after differential)', function () {
    $cli = (new PositionsBuilder)
        ->selectBodies(PlanetBodySelection::SATURN)
        ->differentialTo(PlanetBodySelection::SUN)
        ->midpointTo(PlanetBodySelection::CHIRON)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-DD');
    expect($cli)->not->toContain('-d0');
});

it('keeps differential and midpoint mutually exclusive — last call wins (differential after midpoint)', function () {
    $cli = (new PositionsBuilder)
        ->selectBodies(PlanetBodySelection::MERCURY)
        ->midpointTo(PlanetBodySelection::CHIRON)
        ->differentialTo(PlanetBodySelection::SUN)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-d0');
    expect($cli)->not->toContain('-DD');
});

it('parses a differential row, storing the composite "A-B" name with a null index', function () {
    $builder = (new PositionsBuilder)
        ->selectBodies(PlanetBodySelection::MERCURY)
        ->differentialTo(PlanetBodySelection::SUN);

    $lines = fixtureLines('swetest-differential-mercury-sun.txt');

    $frame = (new PositionsParser)->parse($lines, $builder);

    expect($frame->planet_bodies)->toHaveCount(1);

    $row = $frame->planet_bodies[0];
    expect($row['planet_body']->index)->toBeNull();
    expect($row['planet_body']->name)->toBe('Mer-Sun');
});

it('parses a midpoint row, storing the composite "A/B" name with a null index', function () {
    $builder = (new PositionsBuilder)
        ->selectBodies(PlanetBodySelection::SATURN)
        ->midpointTo(PlanetBodySelection::CHIRON);

    $lines = fixtureLines('swetest-midpoint-saturn-chiron.txt');

    $frame = (new PositionsParser)->parse($lines, $builder);

    expect($frame->planet_bodies)->toHaveCount(1);

    $row = $frame->planet_bodies[0];
    expect($row['planet_body']->index)->toBeNull();
    expect($row['planet_body']->name)->toBe('Sat/Chi');
});

it('computes a real differential ephemeris end-to-end (skips without the swetest binary)', function () {
    $exe = (string) config('swisseph.executable', '');
    if ($exe === '' || ! is_file($exe) || ! is_executable($exe)) {
        $this->markTestSkipped('swetest binary not available in this environment.');
    }

    $frame = (new PositionsBuilder)
        ->setDateTime('2026-01-01 12:00:00', 'UTC')
        ->selectBodies(PlanetBodySelection::MERCURY)
        ->differentialTo(PlanetBodySelection::SUN)
        ->get();

    expect($frame->planet_bodies)->not->toBeEmpty();
    // The composite name must be a difference label "A-B", never a plain body name.
    expect($frame->planet_bodies[0]['planet_body']->name)->toContain('-');
});
