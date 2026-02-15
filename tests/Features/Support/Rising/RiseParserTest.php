<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\RiseSetEvent;
use DivineaLabs\Swisseph\Enums\DiscMode;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Enums\RiseSetEventType;
use DivineaLabs\Swisseph\Support\Rising\RiseParser;
use DivineaLabs\Swisseph\Support\Rising\RiseQuery;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeModeAQuery(
    PlanetBody $body = PlanetBody::SUN,
    string $utcDate = '2026-02-14',
): RiseQuery {
    return new RiseQuery(
        body: $body,
        windowStartUtc: Carbon::parse($utcDate.' 00:00:00', 'UTC'),
        timezone: null,
        localDate: null,
        utcDate: $utcDate,
        longitude: 17.038538,
        latitude: 51.107883,
        elevation: 0.0,
        discMode: DiscMode::BOTTOM,
        noRefraction: false,
        anchorToLocalMidnight: false,
        windowDays: 3,
        stepDays: 1.0,
        atmosphericModel: null,
        observerModel: null,
        opticalModel: null,
    );
}

function makeModeBQuery(
    PlanetBody $body = PlanetBody::SUN,
    string $localDate = '2026-02-14',
    string $timezone = 'Europe/Warsaw',
    string $utcDate = '2026-02-13',
): RiseQuery {
    return new RiseQuery(
        body: $body,
        windowStartUtc: Carbon::parse($utcDate.' 23:00:00', 'UTC'),
        timezone: $timezone,
        localDate: $localDate,
        utcDate: $utcDate,
        longitude: 17.038538,
        latitude: 51.107883,
        elevation: 0.0,
        discMode: DiscMode::BOTTOM,
        noRefraction: false,
        anchorToLocalMidnight: false,
        windowDays: 3,
        stepDays: 1.0,
        atmosphericModel: null,
        observerModel: null,
        opticalModel: null,
    );
}

function warsawFixtureLines(): array
{
    return file(
        __DIR__.'/../../../Fixtures/swetest-rise-warsaw.txt',
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );
}

function tokyoFixtureLines(): array
{
    return file(
        __DIR__.'/../../../Fixtures/swetest-rise-tokyo.txt',
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );
}

// ---------------------------------------------------------------------------
// Line classification / validation — parser never throws
// ---------------------------------------------------------------------------

it('skips geo. lines', function () {
    $lines = ['geo. long 17.038538, lat 51.107883, alt 0.000000'];
    $result = (new RiseParser)->parse($lines, makeModeAQuery());

    expect($result->events)->toBeEmpty();
});

it('skips blank lines', function () {
    $result = (new RiseParser)->parse(['', '   '], makeModeAQuery());

    expect($result->events)->toBeEmpty();
});

it('skips lines with fewer than 6 tokens', function () {
    $result = (new RiseParser)->parse(['rise 14.02.2026 06:10:32.9'], makeModeAQuery());

    expect($result->events)->toBeEmpty();
    expect($result->riseFound)->toBeFalse();
});

it('skips lines where tokens[3] is not "set"', function () {
    $result = (new RiseParser)->parse(
        ['rise     14.02.2026       06:10:32.9    PLACEHOLDER      14.02.2026       16:02:08.3    dt =  09:51:35.4'],
        makeModeAQuery()
    );

    expect($result->events)->toBeEmpty();
});

it('skips lines with invalid date format in tokens[1]', function () {
    $result = (new RiseParser)->parse(
        ['rise     2026-02-14       06:10:32.9    set      14.02.2026       16:02:08.3    dt =  09:51:35.4'],
        makeModeAQuery()
    );

    expect($result->events)->toBeEmpty();
});

it('skips lines with invalid time format in tokens[2]', function () {
    $result = (new RiseParser)->parse(
        ['rise     14.02.2026       6:10:32.9    set      14.02.2026       16:02:08.3    dt =  09:51:35.4'],
        makeModeAQuery()
    );

    expect($result->events)->toBeEmpty();
});

it('returns empty result for entirely empty input without throwing', function () {
    $result = (new RiseParser)->parse([], makeModeAQuery());

    expect($result->events)->toBeEmpty();
    expect($result->riseFound)->toBeFalse();
    expect($result->setFound)->toBeFalse();
});

it('parses only valid lines from a mix of valid and garbage lines', function () {
    $lines = [
        'geo. long 17.038538',
        'garbage line here',
        'rise     14.02.2026       06:10:32.9    set      14.02.2026       16:02:08.3    dt =  09:51:35.4',
        '   ',
        'another bad line',
    ];
    $result = (new RiseParser)->parse($lines, makeModeAQuery(utcDate: '2026-02-14'));

    expect($result->events)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// Datetime parsing — fractional seconds
// ---------------------------------------------------------------------------

it('parses UTC timestamp correctly', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery());
    $rise = $result->rise();

    expect($rise)->not->toBeNull();
    expect($rise->utcAt->format('Y-m-d H:i:s'))->toBe('2026-02-14 06:10:32');
    expect($rise->utcAt->timezone->getName())->toBe('UTC');
});

it('parses single-digit fractional second correctly (.9 → 900000 microseconds)', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery());
    $rise = $result->rise();

    expect($rise->utcAt->microsecond)->toBe(900000);
});

it('parses two-digit fractional second (.94 → 940000 microseconds)', function () {
    $lines = ['rise     14.02.2026       06:10:32.94    set      14.02.2026       16:02:08.3    dt =  09:51:35.4'];
    $result = (new RiseParser)->parse($lines, makeModeAQuery());
    $rise = $result->rise();

    expect($rise->utcAt->microsecond)->toBe(940000);
});

it('parses time without fractional part without error', function () {
    $lines = ['rise     14.02.2026       06:10:32    set      14.02.2026       16:02:08    dt =  09:51:35'];
    $result = (new RiseParser)->parse($lines, makeModeAQuery());
    $rise = $result->rise();

    expect($rise)->not->toBeNull();
    expect($rise->utcAt->microsecond)->toBe(0);
});

// ---------------------------------------------------------------------------
// Body identity stamping
// ---------------------------------------------------------------------------

it('stamps body = SUN on events when query body is SUN', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery(PlanetBody::SUN));

    foreach ($result->events as $event) {
        expect($event->body)->toBe(PlanetBody::SUN);
    }
});

it('stamps body = SATURN on events when query body is SATURN (same fixture)', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery(PlanetBody::SATURN));

    foreach ($result->events as $event) {
        expect($event->body)->toBe(PlanetBody::SATURN);
    }
});

// ---------------------------------------------------------------------------
// Mode A
// ---------------------------------------------------------------------------

it('Mode A: localAt and localDate are null', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery());
    $rise = $result->rise();

    expect($rise->localAt)->toBeNull();
    expect($rise->localDate)->toBeNull();
});

it('Mode A: filters by utcDate — only target day events returned', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery(utcDate: '2026-02-14'));

    expect($result->events)->toHaveCount(2);
    foreach ($result->events as $event) {
        expect($event->utcDate)->toBe('2026-02-14');
    }
});

it('Mode A: multi-line fixture returns 1 RISE + 1 SET for the requested day', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery(utcDate: '2026-02-15'));

    expect($result->riseFound)->toBeTrue();
    expect($result->setFound)->toBeTrue();
    $rise = $result->rise();
    expect($rise->utcAt->format('Y-m-d'))->toBe('2026-02-15');
});

// ---------------------------------------------------------------------------
// Mode B
// ---------------------------------------------------------------------------

it('Mode B: localAt and localDate are non-null', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeBQuery());
    $rise = $result->rise();

    expect($rise->localAt)->not->toBeNull();
    expect($rise->localDate)->not->toBeNull();
});

it('Mode B: localAt timezone matches query timezone', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeBQuery(timezone: 'Europe/Warsaw'));
    $rise = $result->rise();

    expect($rise->localAt->timezone->getName())->toBe('Europe/Warsaw');
});

it('Mode B: Tokyo cross-midnight — correct local day filtered', function () {
    // Tokyo (UTC+9): UTC 2026-02-13 21:24 = local 2026-02-14 06:24
    // The rise event on UTC 2026-02-13 21:24 belongs to local date 2026-02-14
    $query = makeModeBQuery(
        localDate: '2026-02-14',
        timezone: 'Asia/Tokyo',
        utcDate: '2026-02-13',
    );

    $result = (new RiseParser)->parse(tokyoFixtureLines(), $query);

    expect($result->riseFound)->toBeTrue();
    expect($result->setFound)->toBeTrue();

    $rise = $result->rise();
    expect($rise->localDate)->toBe('2026-02-14');

    $set = $result->set();
    expect($set->localDate)->toBe('2026-02-14');
});

// ---------------------------------------------------------------------------
// Accessors and edge cases
// ---------------------------------------------------------------------------

it('empty input returns events=[] with riseFound=false and setFound=false, no exception', function () {
    $result = (new RiseParser)->parse([], makeModeAQuery());

    expect($result->events)->toBeEmpty();
    expect($result->riseFound)->toBeFalse();
    expect($result->setFound)->toBeFalse();
});

it('rise() returns the RISE event', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery());

    expect($result->rise())->toBeInstanceOf(RiseSetEvent::class);
    expect($result->rise()->type)->toBe(RiseSetEventType::RISE);
});

it('set() returns the SET event', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery());

    expect($result->set())->toBeInstanceOf(RiseSetEvent::class);
    expect($result->set()->type)->toBe(RiseSetEventType::SET);
});

it('dayLength() returns CarbonInterval difference between rise and set utcAt', function () {
    $result = (new RiseParser)->parse(warsawFixtureLines(), makeModeAQuery());
    $dl = $result->dayLength();

    expect($dl)->not->toBeNull();
    expect($dl)->toBeInstanceOf(\Carbon\CarbonInterval::class);
    // Roughly 9h51m from the fixture
    expect($dl->totalSeconds)->toBeGreaterThan(0);
});

it('dayLength() returns null when SET event is missing', function () {
    // Only supply a rise-line fixture but query for a day that has no set (simulate by wrong utcDate for set)
    $lines = ['rise     14.02.2026       06:10:32.9    set      15.02.2026       16:02:08.3    dt =  09:51:35.4'];
    $result = (new RiseParser)->parse($lines, makeModeAQuery(utcDate: '2026-02-14'));

    // Rise is on 2026-02-14, set falls on 2026-02-15 — after filter set is absent
    expect($result->setFound)->toBeFalse();
    expect($result->dayLength())->toBeNull();
});
