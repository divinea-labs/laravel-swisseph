<?php

use DivineaLabs\Swisseph\Exceptions\EclipseKindNotSetException;
use DivineaLabs\Swisseph\Exceptions\InvalidEclipseFilterException;
use DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder;

beforeEach(function () {
    config()->set('swisseph.executable', '/bin/swetest');
    config()->set('swisseph.ephemeris_dir', '/ephe');
    config()->set('swisseph.eph_options', []);
});

function eclipseArgs(EclipsesBuilder $b): array
{
    return $b->build()->arguments;
}

it('throws when kind is not set', function () {
    (new EclipsesBuilder)->from('2026-01-01')->build();
})->throws(EclipseKindNotSetException::class);

it('builds solar global args with defaults (count 1)', function () {
    $b = (new EclipsesBuilder)->solar()->from('2026-01-01');

    expect(eclipseArgs($b))->toBe([
        'edir/ephe',
        'solecl',
        'b01.01.2026',
        'n1',
    ]);
});

it('builds lunar global args with count and backward', function () {
    $b = (new EclipsesBuilder)->lunar()->from('2026-01-01')->count(5)->backward();

    expect(eclipseArgs($b))->toBe([
        'edir/ephe',
        'lunecl',
        'b01.01.2026',
        'n5',
        'bwd',
    ]);
});

it('builds solar local args with geopos floats', function () {
    $b = (new EclipsesBuilder)->solar()->local(21.0, 52.2, 100.0)->from('2026-08-01')->count(2);

    expect(eclipseArgs($b))->toBe([
        'edir/ephe',
        'solecl',
        'local',
        'geopos21,52.2,100',
        'b01.08.2026',
        'n2',
    ]);
});

it('defaults elevation to zero in local geopos', function () {
    $b = (new EclipsesBuilder)->solar()->local(21.0, 52.2)->from('2026-08-01');

    expect(eclipseArgs($b))->toContain('geopos21,52.2,0');
});

it('emits solar type filters', function () {
    expect(eclipseArgs((new EclipsesBuilder)->solar()->from('2026-01-01')->onlyTotal()))->toContain('total');
    expect(eclipseArgs((new EclipsesBuilder)->solar()->from('2026-01-01')->onlyPartial()))->toContain('partial');
    expect(eclipseArgs((new EclipsesBuilder)->solar()->from('2026-01-01')->onlyAnnular()))->toContain('annular');
    expect(eclipseArgs((new EclipsesBuilder)->solar()->from('2026-01-01')->onlyHybrid()))->toContain('anntot');
    expect(eclipseArgs((new EclipsesBuilder)->solar()->global()->from('2026-01-01')->onlyCentral()))->toContain('central');
    expect(eclipseArgs((new EclipsesBuilder)->solar()->global()->from('2026-01-01')->onlyNonCentral()))->toContain('noncentral');
});

it('emits lunar type filters', function () {
    expect(eclipseArgs((new EclipsesBuilder)->lunar()->from('2026-01-01')->onlyTotal()))->toContain('total');
    expect(eclipseArgs((new EclipsesBuilder)->lunar()->from('2026-01-01')->onlyPartial()))->toContain('partial');
    expect(eclipseArgs((new EclipsesBuilder)->lunar()->from('2026-01-01')->onlyPenumbral()))->toContain('penumbral');
});

it('rejects annular on lunar', function () {
    (new EclipsesBuilder)->lunar()->from('2026-01-01')->onlyAnnular()->build();
})->throws(InvalidEclipseFilterException::class);

it('rejects hybrid on lunar', function () {
    (new EclipsesBuilder)->lunar()->from('2026-01-01')->onlyHybrid()->build();
})->throws(InvalidEclipseFilterException::class);

it('rejects central on lunar', function () {
    (new EclipsesBuilder)->lunar()->from('2026-01-01')->onlyCentral()->build();
})->throws(InvalidEclipseFilterException::class);

it('rejects noncentral on lunar', function () {
    (new EclipsesBuilder)->lunar()->from('2026-01-01')->onlyNonCentral()->build();
})->throws(InvalidEclipseFilterException::class);

it('rejects penumbral on solar', function () {
    (new EclipsesBuilder)->solar()->from('2026-01-01')->onlyPenumbral()->build();
})->throws(InvalidEclipseFilterException::class);

it('keeps the configured executable', function () {
    expect((new EclipsesBuilder)->solar()->from('2026-01-01')->build()->executable)->toBe('/bin/swetest');
});

it('throws InvalidEclipseFilterException when lunar and local are both set', function () {
    (new EclipsesBuilder)->lunar()->local(21.0, 52.2)->from('2026-01-01')->build();
})->throws(InvalidEclipseFilterException::class, 'Lunar eclipses do not support local() in SP1; use global().');
