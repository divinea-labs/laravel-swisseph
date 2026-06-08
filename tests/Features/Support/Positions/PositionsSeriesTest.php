<?php

use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Exceptions\InvalidStepCountException;
use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;
use DivineaLabs\Swisseph\Support\Positions\PositionsParser;

it('emits -n<count> for a step count without a step size', function () {
    $cmd = (new PositionsBuilder)
        ->steps(5)
        ->build()
        ->toProcessArray();

    expect($cmd)->toContain('-n5');
    // No -s when step size omitted
    expect(collect($cmd)->contains(fn ($a) => str_starts_with($a, '-s')))->toBeFalse();
});

it('emits -n<count> and -s<step> when a step size is given', function () {
    $cmd = (new PositionsBuilder)
        ->steps(5, '1')
        ->build()
        ->toProcessArray();

    expect($cmd)->toContain('-n5');
    expect($cmd)->toContain('-s1');
});

it('passes raw swetest step-size tokens through verbatim (each suffix)', function (string $token) {
    $cmd = (new PositionsBuilder)
        ->steps(3, $token)
        ->build()
        ->toProcessArray();

    expect($cmd)->toContain('-s'.$token);
})->with([
    '1',     // days (bare)
    '15m',   // minutes (15 min) — NO hour suffix exists; 360m = 6h
    '6',     // days
    '3mo',   // months
    '10y',   // years
    '1s',    // seconds
]);

it('auto-includes the T time column so each row is timestamp-prefixed', function () {
    $cmd = (new PositionsBuilder)
        ->steps(2, '1')
        ->build()
        ->toProcessArray();

    // The -f sequence must begin with T (date column) then the default p P l s.
    $f = collect($cmd)->first(fn ($a) => str_starts_with($a, '-f'));

    expect($f)->not->toBeNull();
    expect($f)->toStartWith('-fT');
    expect($f)->toContain(AstroProperties::DATE_FORMAT_DD_MM_YYYY->value); // 'T'
});

it('does not duplicate the T column if the caller already added it', function () {
    $cmd = (new PositionsBuilder)
        ->withProperties(AstroProperties::DATE_FORMAT_DD_MM_YYYY)
        ->steps(2, '1')
        ->build()
        ->toProcessArray();

    $f = collect($cmd)->first(fn ($a) => str_starts_with($a, '-f'));

    expect(substr_count($f, 'T'))->toBe(1);
});

it('throws when the step count is less than 1', function () {
    (new PositionsBuilder)->steps(0);
})->throws(InvalidStepCountException::class);

it('T column leads the -f sequence even when DATE_FORMAT_DD_MM_YYYY was added via withProperties() before steps()', function () {
    $cmd = (new PositionsBuilder)
        ->withProperties(AstroProperties::DATE_FORMAT_DD_MM_YYYY)
        ->steps(3)
        ->getCliCommand();

    // T must be the very first character after -f
    expect($cmd)->toContain('-fT');

    // The -f token must start with -fT (T is first, not somewhere in the middle)
    preg_match('/-f\S+/', $cmd, $m);
    expect($m[0] ?? '')->toStartWith('-fT');

    // Exactly one T in the -f token (no duplication)
    expect(substr_count($m[0] ?? '', 'T'))->toBe(1);
});

it('parseSeries() skips rows whose leading token is not a valid timestamp instead of throwing', function () {
    $builder = (new PositionsBuilder)
        ->setLocation(0.0, 0.0, 'Greenwich')
        ->steps(2, '1');

    // Rows where the first PPP-delimited token is a planet index (not a timestamp).
    // This simulates what happens when T is not first in the -f sequence.
    $malformedLines = [
        '0PPP Sun              PPP281.0780451PPP  1.0189221',
        '1PPP Moon             PPP 79.0984707PPP 13.1234567',
    ];

    // Must not throw; should return a series (possibly empty, skipping the bad rows).
    $series = (new PositionsParser)->parseSeries($malformedLines, $builder);

    expect($series->count())->toBe(0);
});

// ─── Parser tests ──────────────────────────────────────────

it('groups multi-frame batch output into N frames x M bodies', function () {
    $builder = (new PositionsBuilder)
        ->setLocation(0.0, 0.0, 'Greenwich')
        ->steps(5, '1'); // forces T column into the -f sequence used by the parser

    $lines = fixtureLines('swetest-series-n5-s1.txt');

    $series = (new PositionsParser)->parseSeries($lines, $builder);

    // 5 daily frames
    expect($series->count())->toBe(5);

    // Frame timestamps (UTC) in order
    expect($series->first()?->date->format('d.m.Y H:i:s'))->toBe('01.01.2026 12:00:00');
    expect($series->last()?->date->format('d.m.Y H:i:s'))->toBe('05.01.2026 12:00:00');

    // Each frame has exactly the two captured bodies
    foreach ($series->frames as $frame) {
        expect($frame->planet_bodies)->toHaveCount(2);
    }

    // Lookup by timestamp token
    $f2 = $series->at('02.01.2026 12:00:00');
    expect($f2)->not->toBeNull();

    // Per-frame body values — frame 2 Sun + Moon
    $byName = collect($f2->planet_bodies)->keyBy(fn ($r) => $r['planet_body']->name);

    $sun = collect($byName['Sun']['properties'])->keyBy('property');
    expect($sun['longitude_decimal']->value)->toBe('282.0969055');
    expect($sun['speed_longitude_decimal']->value)->toBe('1.0189221');

    $moon = collect($byName['Moon']['properties'])->keyBy('property');
    expect($moon['longitude_decimal']->value)->toBe('87.0996296');
    expect($moon['speed_longitude_decimal']->value)->toBe('13.0011544');

    // First frame Sun longitude (sanity on grouping order)
    $f1Sun = collect(
        collect($series->first()->planet_bodies)
            ->firstWhere(fn ($r) => $r['planet_body']->name === 'Sun')['properties']
    )->keyBy('property');
    expect($f1Sun['longitude_decimal']->value)->toBe('281.0780451');
});

// ─── Integration test (self-skipping) ──────────────────────

it('runs a real 5-step daily batch in one process', function () {
    $exe = (string) config('swisseph.executable', '');
    if ($exe === '' || ! is_file($exe)) {
        test()->markTestSkipped('swetest binary not available');
    }

    $series = Swisseph::positions()
        ->setLocation(0.0, 0.0, 'Greenwich')
        ->setDateTime('2026-01-01 12:00:00', 'UTC')
        ->steps(5, '1')
        ->getSeries();

    expect($series->count())->toBe(5);
    expect($series->first()?->date->format('d.m.Y'))->toBe('01.01.2026');
    expect($series->last()?->date->format('d.m.Y'))->toBe('05.01.2026');

    foreach ($series->frames as $frame) {
        expect(count($frame->planet_bodies))->toBeGreaterThan(0);
    }
})->skip(fn () => ! is_file((string) config('swisseph.executable', '')), 'swetest binary not available');
