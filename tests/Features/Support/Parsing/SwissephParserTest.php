<?php

use DivineaLabs\Swisseph\Enums\HouseSystems;
use DivineaLabs\Swisseph\Support\Command\SwissephCommandBuilder;
use DivineaLabs\Swisseph\Support\Parsing\SwissephParser;

it('parses swetest output into planets and houses', function () {
    $builder = new SwissephCommandBuilder;
    $builder->setLocation(17.038538, 51.107883, 'Wroclaw');
    $builder->withHouses(HouseSystems::KOCH);

    $lines = fixtureLines('swetest-koch.txt');

    $parser = new SwissephParser;
    $frame = $parser->parse($lines, $builder);

    expect($frame->place)->toBe('Wroclaw');
    expect($frame->planet_bodies)->toHaveCount(13); // Sun..Mean Apogee
    expect($frame->houses)->toHaveCount(20);        // House1..12 + Asc..Polar Asc
});

it('parses planet properties according to -f sequence', function () {
    $builder = new SwissephCommandBuilder;
    $builder->setLocation(17.038538, 51.107883, 'Wroclaw');
    $builder->withHouses(HouseSystems::KOCH);

    $lines = fixtureLines('swetest-koch.txt');

    $parser = new SwissephParser;
    $frame = $parser->parse($lines, $builder);

    $sun = $frame->planet_bodies[0];

    expect($sun['planet_body']->index)->toBe(0);
    expect($sun['planet_body']->name)->toBe('Sun');
    expect($sun['properties'])->toHaveCount(5);

    $byKey = collect($sun['properties'])->keyBy('property');

    expect($byKey['longitude_decimal']->value)->toBe('3.4519333');
    expect($byKey['speed_longitude_decimal']->value)->toBe('0.9918181');
    expect($byKey['house_position_degrees']->value)->toBe("139°21' 8.5742");
    expect($byKey['house_position_degrees_decimal']->value)->toBe('139.3523817');
    expect($byKey['house_number_decimal']->value)->toBe('5.6450794');
});

it('parses house rows and omits house_number_decimal for house points', function () {
    $builder = new SwissephCommandBuilder;
    $builder->setLocation(17.038538, 51.107883, 'Wroclaw');
    $builder->withHouses(HouseSystems::KOCH);

    $lines = fixtureLines('swetest-koch.txt');

    $parser = new SwissephParser;
    $frame = $parser->parse($lines, $builder);

    $house1 = $frame->houses[0];

    expect($house1['house']->index)->toBe(1);
    expect($house1['house']->name)->toBe('House 1');
    expect($house1['properties'])->toHaveCount(4);

    // pierwszy property to longitude_decimal
    expect($house1['properties'][0]['name'])->toBe('longitude_decimal');
    expect($house1['properties'][0]['value'])->toBe('217.9874824');

    // Make sure there is no house_number_decimal
    expect(collect($house1['properties'])->pluck('name')->all())
        ->not->toContain('house_number_decimal');
});
