<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Support\Meridian\MeridianTransitBuilder;
use DivineaLabs\Swisseph\Support\Meridian\MeridianTransitParser;

it('parses upper and lower meridian transits per day, skipping the geo header', function () {
    $lines = fixtureLines('swetest-meridian-sun-warsaw.txt');

    $collection = (new MeridianTransitParser)->parse($lines, PlanetBody::SUN);

    expect($collection->count())->toBe(2); // geo. long line is tolerated/skipped

    $day1 = $collection->all()[0];
    expect($day1->body)->toBe(PlanetBody::SUN);
    expect($day1->date)->toBe('2026-01-01');
    expect($day1->upperTransitAt->format('Y-m-d H:i:s'))->toBe('2026-01-01 10:39:32');
    expect($day1->upperTransitAt->micro)->toBe(300000); // .3 second
    expect($day1->lowerTransitAt->format('Y-m-d H:i:s'))->toBe('2026-01-01 22:39:46');
    expect($day1->lowerTransitAt->micro)->toBe(300000); // .3 second

    $day2 = $collection->all()[1];
    expect($day2->date)->toBe('2026-01-02');
    expect($day2->upperTransitAt->format('Y-m-d H:i:s'))->toBe('2026-01-02 10:40:00');
    expect($day2->upperTransitAt->micro)->toBe(300000); // 10:40:00.3
    expect($day2->lowerTransitAt->format('Y-m-d H:i:s'))->toBe('2026-01-02 22:40:14');
    expect($day2->lowerTransitAt->micro)->toBe(100000); // .1 second
});

it('returns an empty collection for input with no transit lines', function () {
    $collection = (new MeridianTransitParser)->parse(['geo. long 1, lat 2, alt 0', 'garbage'], PlanetBody::SUN);

    expect($collection->count())->toBe(0);
    expect($collection->first())->toBeNull();
});

it('computes real meridian transits end-to-end (skips without the swetest binary)', function () {
    $exe = (string) config('swisseph.executable', '');
    if ($exe === '' || ! is_file($exe) || ! is_executable($exe)) {
        $this->markTestSkipped('swetest binary not available in this environment.');
    }

    $collection = (new MeridianTransitBuilder)
        ->at(21.0, 52.2, 100.0)
        ->from('2026-01-01')
        ->count(2)
        ->get();

    expect($collection->count())->toBeGreaterThan(0);

    $first = $collection->first();
    expect($first->upperTransitAt->lessThan($first->lowerTransitAt))->toBeTrue();
});
