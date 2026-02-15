<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Support\Rising\RiseCommandBuilder;
use DivineaLabs\Swisseph\Support\Rising\RiseQuery;

afterEach(function () {
    Carbon::setTestNow();
});

// ---------------------------------------------------------------------------
// Default — no setDateTime call
// ---------------------------------------------------------------------------

it('defaults to today UTC start-of-day when setDateTime was never called', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 14, 12, 0, 0, 'UTC'));

    $builder = new RiseCommandBuilder;
    [, $query] = $builder->buildWithQuery();

    expect($query->utcDate)->toBe('2026-02-14');
    expect($query->isModeB())->toBeFalse();
    expect($query->localDate)->toBeNull();
});

it('defaults to Sun when buildWithQuery is called without a body argument', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 14, 0, 0, 0, 'UTC'));

    $builder = new RiseCommandBuilder;
    [$command, $query] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('p0');
    expect($query->body)->toBe(PlanetBody::SUN);
});

// ---------------------------------------------------------------------------
// Body selection
// ---------------------------------------------------------------------------

it('emits p0 for Sun', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 14, 0, 0, 0, 'UTC'));

    [$command] = (new RiseCommandBuilder)->buildWithQuery(PlanetBody::SUN);

    expect($command->arguments)->toContain('p0');
});

it('emits p6 for Saturn', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 14, 0, 0, 0, 'UTC'));

    [$command] = (new RiseCommandBuilder)->buildWithQuery(PlanetBody::SATURN);

    expect($command->arguments)->toContain('p6');
});

it('emits p1 for Moon', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 14, 0, 0, 0, 'UTC'));

    [$command] = (new RiseCommandBuilder)->buildWithQuery(PlanetBody::MOON);

    expect($command->arguments)->toContain('p1');
});

it('emits p15 for Chiron', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 14, 0, 0, 0, 'UTC'));

    [$command] = (new RiseCommandBuilder)->buildWithQuery(PlanetBody::CHIRON);

    expect($command->arguments)->toContain('p15');
});

it('two sequential calls with different bodies do not bleed state', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 14, 0, 0, 0, 'UTC'));

    $builder = new RiseCommandBuilder;

    [$cmd1, $q1] = $builder->buildWithQuery(PlanetBody::SUN);
    [$cmd2, $q2] = $builder->buildWithQuery(PlanetBody::SATURN);

    expect($cmd1->arguments)->toContain('p0');
    expect($cmd2->arguments)->toContain('p6');
    expect($q1->body)->toBe(PlanetBody::SUN);
    expect($q2->body)->toBe(PlanetBody::SATURN);
});

// ---------------------------------------------------------------------------
// Mode A / Mode B
// ---------------------------------------------------------------------------

it('is Mode A when no timezone given', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');

    [, $query] = $builder->buildWithQuery();

    expect($query->isModeB())->toBeFalse();
    expect($query->timezone)->toBeNull();
    expect($query->localDate)->toBeNull();
});

it('is Mode A when UTC timezone given', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14', 'UTC');

    [, $query] = $builder->buildWithQuery();

    expect($query->isModeB())->toBeFalse();
});

it('is Mode B for a non-UTC timezone', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14', 'Europe/Warsaw');

    [, $query] = $builder->buildWithQuery();

    expect($query->isModeB())->toBeTrue();
    expect($query->timezone)->toBe('Europe/Warsaw');
    expect($query->localDate)->toBe('2026-02-14');
});

it('Mode A: b token uses UTC date from input', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('b14.02.2026');
});

it('Mode B: b token uses UTC date of local midnight (Warsaw UTC+1 → UTC prev day)', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14', 'Europe/Warsaw');

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('b13.02.2026');
});

it('Mode B + anchorToLocalMidnight emits ut token', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14', 'Europe/Warsaw');
    $builder->anchorToLocalMidnight();

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('ut23:00:00');
});

it('anchorToLocalMidnight is a no-op in Mode A', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');
    $builder->anchorToLocalMidnight();

    [$command] = $builder->buildWithQuery();

    $utTokens = array_filter($command->arguments, fn ($a) => str_starts_with($a, 'ut'));
    expect($utTokens)->toBeEmpty();
});

it('time component is ignored — same output for 12:00 and 00:00 on same date (Mode A)', function () {
    $b1 = (new RiseCommandBuilder)->setDateTime('2026-02-14 00:00', null);
    $b2 = (new RiseCommandBuilder)->setDateTime('2026-02-14 12:00', null);

    [$c1] = $b1->buildWithQuery();
    [$c2] = $b2->buildWithQuery();

    expect($c1->arguments)->toContain('b14.02.2026');
    expect($c2->arguments)->toContain('b14.02.2026');
});

// ---------------------------------------------------------------------------
// Eph options
// ---------------------------------------------------------------------------

it('withEphOptions adds token to args', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');
    $builder->withEphOptions(EphOptions::NO_ABERRATION);

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('noaberr');
});

it('de-duplicates eph options — calling same option twice emits once', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');
    $builder->withEphOptions(EphOptions::NO_ABERRATION);
    $builder->withEphOptions(EphOptions::NO_ABERRATION);

    [$command] = $builder->buildWithQuery();

    expect(array_count_values($command->arguments)['noaberr'])->toBe(1);
});

// ---------------------------------------------------------------------------
// Atmospheric / observer / optical models
// ---------------------------------------------------------------------------

it('setAtmosphericModel emits at token', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');
    $builder->setAtmosphericModel(1013.25, 15, 40, 0);

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('at1013.25,15,40,0');
});

it('no at token when setAtmosphericModel not called', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');

    [$command] = $builder->buildWithQuery();

    $atTokens = array_filter($command->arguments, fn ($a) => str_starts_with($a, 'at'));
    expect($atTokens)->toBeEmpty();
});

it('setObserverModel emits obs token', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');
    $builder->setObserverModel(50, 1.2);

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('obs50,1.2');
});

it('no obs token when setObserverModel not called', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');

    [$command] = $builder->buildWithQuery();

    $tokens = array_filter($command->arguments, fn ($a) => str_starts_with($a, 'obs'));
    expect($tokens)->toBeEmpty();
});

it('setOpticalModel emits opt token', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');
    $builder->setOpticalModel(50, 1.2, false, 1, 0, 0.5);

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('opt50,1.2,0,1,0,0.5');
});

it('no opt token when setOpticalModel not called', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');

    [$command] = $builder->buildWithQuery();

    $tokens = array_filter($command->arguments, fn ($a) => str_starts_with($a, 'opt'));
    expect($tokens)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Backward search
// ---------------------------------------------------------------------------

it('searchBackward emits bwd token', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');
    $builder->searchBackward();

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('bwd');
});

it('no bwd token when searchBackward not called', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->not->toContain('bwd');
});

// ---------------------------------------------------------------------------
// General token assertions
// ---------------------------------------------------------------------------

it('always emits rise token', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('rise');
});

it('default disc mode is discbottom', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('discbottom');
});

it('withoutRefraction emits norefrac token', function () {
    $builder = new RiseCommandBuilder;
    $builder->setDateTime('2026-02-14');
    $builder->withoutRefraction();

    [$command] = $builder->buildWithQuery();

    expect($command->arguments)->toContain('norefrac');
});

it('buildWithQuery returns a two-element tuple of SwissephCommand and RiseQuery', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 14, 0, 0, 0, 'UTC'));

    $result = (new RiseCommandBuilder)->buildWithQuery(PlanetBody::SATURN);

    expect($result)->toBeArray()->toHaveCount(2);
    expect($result[0])->toBeInstanceOf(\DivineaLabs\Swisseph\Data\SwissephCommand::class);
    expect($result[1])->toBeInstanceOf(RiseQuery::class);
});

it('RiseQuery body matches the argument passed to buildWithQuery', function () {
    Carbon::setTestNow(Carbon::create(2026, 2, 14, 0, 0, 0, 'UTC'));

    [, $query] = (new RiseCommandBuilder)->buildWithQuery(PlanetBody::SATURN);

    expect($query->body)->toBe(PlanetBody::SATURN);
});
