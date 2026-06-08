<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\AstroTimeFrame;
use DivineaLabs\Swisseph\Data\AstroTimeSeries;

function makeFrame(string $iso): AstroTimeFrame
{
    return new AstroTimeFrame(
        id: null,
        place: 'Greenwich',
        date: Carbon::parse($iso, 'UTC'),
        longitude: 0.0,
        latitude: 0.0,
        house_system: null,
        planet_bodies: [],
        houses: [],
    );
}

it('reports count and first/last frames', function () {
    $a = makeFrame('2026-01-01 12:00:00');
    $b = makeFrame('2026-01-02 12:00:00');
    $c = makeFrame('2026-01-03 12:00:00');

    $series = new AstroTimeSeries(frames: [$a, $b, $c]);

    expect($series->count())->toBe(3);
    expect($series->first()?->date->toDateTimeString())->toBe('2026-01-01 12:00:00');
    expect($series->last()?->date->toDateTimeString())->toBe('2026-01-03 12:00:00');
});

it('finds a frame by its swetest timestamp string', function () {
    $series = new AstroTimeSeries(frames: [
        makeFrame('2026-01-01 12:00:00'),
        makeFrame('2026-01-02 12:00:00'),
    ]);

    expect($series->at('02.01.2026 12:00:00')?->date->toDateTimeString())
        ->toBe('2026-01-02 12:00:00');
    expect($series->at('09.09.2099 00:00:00'))->toBeNull();
});

it('returns null helpers for an empty series', function () {
    $series = new AstroTimeSeries(frames: []);

    expect($series->count())->toBe(0);
    expect($series->first())->toBeNull();
    expect($series->last())->toBeNull();
});
