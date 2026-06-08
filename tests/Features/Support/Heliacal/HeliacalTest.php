<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\HeliacalEventType;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\HeliacalGeoPositionNotSetException;
use DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder;
use DivineaLabs\Swisseph\Support\Heliacal\HeliacalParser;

it('builds a -hev command for a planet at a geographic position', function () {
    $cli = (new HeliacalBuilder)
        ->forBody(PlanetBody::VENUS)
        ->at(21.0, 52.2, 100.0)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-hev');
    expect($cli)->toContain('-p3'); // Venus
    expect($cli)->toContain('-geopos21,52.2,100');
    expect($cli)->toContain('-n1');
});

it('builds a -hev command for a fixed star', function () {
    $cli = (new HeliacalBuilder)
        ->forStar('Sirius')
        ->at(21.0, 52.2)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-pf');
    expect($cli)->toContain('-xfSirius');
});

it('throws when no geographic position is set', function () {
    expect(fn () => (new HeliacalBuilder)->forBody(PlanetBody::VENUS)->build())
        ->toThrow(HeliacalGeoPositionNotSetException::class);
});

it('emits atmospheric, observer and optical models', function () {
    $cli = (new HeliacalBuilder)
        ->forBody(PlanetBody::VENUS)
        ->at(21.0, 52.2)
        ->withAtmosphere(1013.25, 15.0, 40.0, 0.0)
        ->withObserver(45.0, 1.2)
        ->withOptics(45.0, 1.0, true, 1.0, 0.0, 0.0)
        ->build()
        ->toCliString();

    expect($cli)->toContain('-at1013.25,15,40,0');
    expect($cli)->toContain('-obs45,1.2');
    expect($cli)->toContain('-opt45,1,1,1,0,0');
});

it('parses all four heliacal events for Venus, skipping the geo header', function () {
    $lines = fixtureLines('swetest-heliacal-venus.txt');

    $collection = (new HeliacalParser)->parse($lines);

    expect($collection->count())->toBe(4);

    $rising = $collection->all()[0];
    expect($rising->body)->toBe('Venus');
    expect($rising->type)->toBe(HeliacalEventType::HELIACAL_RISING);
    expect($rising->at->format('Y-m-d H:i:s'))->toBe('2026-11-02 04:41:55');
    expect($rising->at->micro)->toBe(900000); // .9 second
    expect($rising->julianDay)->toBe(2461346.69579);
    expect($rising->optimumAt->format('Y-m-d H:i:s'))->toBe('2026-11-02 04:56:15');
    expect($rising->optimumAt->micro)->toBe(900000); // opt 04:56:15.9
    expect($rising->endAt->format('Y-m-d H:i:s'))->toBe('2026-11-02 05:17:59');
    expect($rising->endAt->micro)->toBe(900000); // end 05:17:59.9
    expect($rising->durationMinutes)->toBe(36.1);

    expect($collection->all()[1]->type)->toBe(HeliacalEventType::MORNING_LAST);
    expect($collection->all()[2]->type)->toBe(HeliacalEventType::EVENING_FIRST);

    $setting = $collection->all()[3];
    expect($setting->type)->toBe(HeliacalEventType::HELIACAL_SETTING);
    expect($setting->at->format('Y-m-d H:i:s'))->toBe('2028-05-23 18:56:52');
    expect($setting->at->micro)->toBe(600000); // 18:56:52.6
    expect($setting->durationMinutes)->toBe(42.0);
});

it('filters events by type', function () {
    $collection = (new HeliacalParser)->parse(fixtureLines('swetest-heliacal-venus.txt'));

    expect($collection->ofType(HeliacalEventType::EVENING_FIRST))->toHaveCount(1);
    expect($collection->ofType(HeliacalEventType::EVENING_FIRST)[0]->type)
        ->toBe(HeliacalEventType::EVENING_FIRST);
});

it('computes real heliacal events end-to-end (skips without the swetest binary)', function () {
    $exe = (string) config('swisseph.executable', '');
    if ($exe === '' || ! is_file($exe) || ! is_executable($exe)) {
        $this->markTestSkipped('swetest binary not available in this environment.');
    }

    $collection = (new HeliacalBuilder)
        ->forBody(PlanetBody::VENUS)
        ->at(21.0, 52.2, 100.0)
        ->from('2026-01-01')
        ->get();

    expect($collection->count())->toBeGreaterThan(0);
    expect($collection->first()->body)->not->toBe('');
});
