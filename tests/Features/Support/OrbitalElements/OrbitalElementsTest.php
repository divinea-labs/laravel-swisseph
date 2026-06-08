<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\OrbitalElementsBodyNotSetException;
use DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsBuilder;
use DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsParser;

it('builds an -orbel command for a body with a TT reference date (no -ut)', function () {
    $cli = (new OrbitalElementsBuilder)
        ->forBody(PlanetBody::MARS)
        ->from('2026-01-01')
        ->build()
        ->toCliString();

    expect($cli)->toContain('-orbel');
    expect($cli)->toContain('-p4');        // Mars
    expect($cli)->toContain('-b01.01.2026');
    expect($cli)->not->toContain('-ut');   // -orbel uses TT, not UT
});

it('throws when no body is set', function () {
    expect(fn () => (new OrbitalElementsBuilder)->from('2026-01-01')->build())
        ->toThrow(OrbitalElementsBodyNotSetException::class);
});

it('parses every orbital element from the Mars -orbel block, skipping header lines', function () {
    $lines = fixtureLines('swetest-orbel-mars.txt');

    $elements = (new OrbitalElementsParser)->parse($lines, PlanetBody::MARS);

    expect($elements->body)->toBe(PlanetBody::MARS);
    expect($elements->semiAxis)->toBe(1.523694);
    expect($elements->eccentricity)->toBe(0.093485);
    expect($elements->inclination)->toBe(1.847499);
    expect($elements->ascendingNode)->toBe(49.482984);
    expect($elements->argPericenter)->toBe(286.623510);
    expect($elements->pericenter)->toBe(336.106494);
    expect($elements->meanLongitude)->toBe(291.928275);
    expect($elements->meanAnomaly)->toBe(315.821781);
    expect($elements->eccentricAnomaly)->toBe(311.830723);
    expect($elements->trueAnomaly)->toBe(307.703699);
    expect($elements->timePericenterJd)->toBe(2460438.822970);
    expect($elements->timePericenterCivil)->toContain('8.05.2024');
    expect($elements->timePericenterCivil)->toContain('07:45:04.6');
    expect($elements->distPericenter)->toBe(1.381252);
    expect($elements->distApocenter)->toBe(1.666136);
    expect($elements->meanDailyMotion)->toBe(0.524032);
    expect($elements->siderealPeriodYears)->toBe(1.880890);
    expect($elements->tropicalPeriodYears)->toBe(1.880829);
    expect($elements->synodicCycleDays)->toBe(779.936372);
});

it('computes real orbital elements end-to-end (skips without the swetest binary)', function () {
    $exe = (string) config('swisseph.executable', '');
    if ($exe === '' || ! is_file($exe) || ! is_executable($exe)) {
        $this->markTestSkipped('swetest binary not available in this environment.');
    }

    $elements = (new OrbitalElementsBuilder)
        ->forBody(PlanetBody::MARS)
        ->from('2026-01-01')
        ->get();

    expect($elements->body)->toBe(PlanetBody::MARS);
    expect($elements->semiAxis)->toBeGreaterThan(0.0);
    expect($elements->eccentricity)->toBeGreaterThanOrEqual(0.0);
});
