<?php

declare(strict_types=1);

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\OccultationTargetNotSetException;
use DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder;

beforeEach(function () {
    config()->set('swisseph.executable', '/bin/swetest');
    config()->set('swisseph.ephemeris_dir', '/ephe');
    config()->set('swisseph.eph_options', []);
});

function buildArgs(OccultationsBuilder $builder): array
{
    return $builder->build()->arguments;
}

it('throws when no target is set', function () {
    $builder = new OccultationsBuilder;

    $builder->build();
})->throws(OccultationTargetNotSetException::class);

it('emits a star target via -pf -xf<name> and always -occult, global by default', function () {
    $builder = (new OccultationsBuilder)->forStar('Aldebaran');

    $args = buildArgs($builder);

    expect($args)->toContain('occult');
    expect($args)->toContain('pf');
    expect($args)->toContain('xfAldebaran');
    expect($args)->toContain('n1');               // default count
    expect($args)->not->toContain('local');       // global default
});

it('emits a planet target via -p<value>', function () {
    $builder = (new OccultationsBuilder)->forBody(PlanetBody::JUPITER);

    $args = buildArgs($builder);

    expect($args)->toContain('occult');
    expect($args)->toContain('p5');                // Jupiter = 5
    expect($args)->not->toContain('pf');
});

it('emits -local -geopos<lon>,<lat>,<elev> for local scope with float params', function () {
    $builder = (new OccultationsBuilder)
        ->forStar('Aldebaran')
        ->local(21.0, 52.2, 100.0);

    $args = buildArgs($builder);

    expect($args)->toContain('local');
    expect($args)->toContain('geopos21,52.2,100');
});

it('defaults elevation to 0 in local scope', function () {
    $builder = (new OccultationsBuilder)
        ->forStar('Aldebaran')
        ->local(21.0, 52.2);

    expect(buildArgs($builder))->toContain('geopos21,52.2,0');
});

it('emits -b<d.m.Y> from from()', function () {
    $builder = (new OccultationsBuilder)
        ->forStar('Aldebaran')
        ->from('2033-08-01');

    expect(buildArgs($builder))->toContain('b01.08.2033');
});

it('accepts a Carbon for from()', function () {
    $builder = (new OccultationsBuilder)
        ->forStar('Aldebaran')
        ->from(Carbon::parse('2033-08-01', 'UTC'));

    expect(buildArgs($builder))->toContain('b01.08.2033');
});

it('emits -n<count> from count()', function () {
    $builder = (new OccultationsBuilder)
        ->forStar('Aldebaran')
        ->count(5);

    expect(buildArgs($builder))->toContain('n5');
});

it('emits -bwd from backward()', function () {
    $builder = (new OccultationsBuilder)
        ->forStar('Aldebaran')
        ->backward();

    expect(buildArgs($builder))->toContain('bwd');
});

it('does not emit -bwd by default', function () {
    $builder = (new OccultationsBuilder)->forStar('Aldebaran');

    expect(buildArgs($builder))->not->toContain('bwd');
});

it('emits the executable and ephemeris dir from config', function () {
    $builder = (new OccultationsBuilder)->forStar('Aldebaran');
    $command = $builder->build();

    expect($command->executable)->toBe('/bin/swetest');
    expect($command->arguments)->toContain('edir/ephe');
});

it('propagates eph options through the shared trait', function () {
    $builder = (new OccultationsBuilder)
        ->forStar('Aldebaran')
        ->withEphOptions(EphOptions::TRUE_POSITIONS);

    expect(buildArgs($builder))->toContain('true');
});

it('combines star target, local scope, from, count and backward', function () {
    $builder = (new OccultationsBuilder)
        ->forStar('Aldebaran')
        ->local(21.0, 52.2, 100.0)
        ->from('2034-01-01')
        ->count(3)
        ->backward();

    $args = buildArgs($builder);

    expect($args)->toContain('occult');
    expect($args)->toContain('pf');
    expect($args)->toContain('xfAldebaran');
    expect($args)->toContain('local');
    expect($args)->toContain('geopos21,52.2,100');
    expect($args)->toContain('b01.01.2034');
    expect($args)->toContain('n3');
    expect($args)->toContain('bwd');
});
