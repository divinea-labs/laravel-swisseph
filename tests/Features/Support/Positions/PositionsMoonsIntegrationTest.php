<?php

use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;

/**
 * Integration test for the planetary-moon selector.
 *
 * Self-skips when no swetest binary is configured/available. When it runs, it
 * captures live output and asserts the shape of a planetary-moon position row
 * (in lieu of a hard-coded fixture — see the SP3 plan deferred-fixture note).
 *
 * NOTE: Real swetest output for -pv -xv<number> was NOT captured for the SP3
 * reference. Therefore SP3 ships selectMoon() as a thin selector that reuses
 * the standard position-row parser, with NO hard-coded unit fixture. When this
 * integration test first runs green against a real binary, the captured block
 * SHOULD be promoted into a tests/Fixtures/swetest-moon-<n>.txt fixture and a
 * unit parser test added (follow-up task).
 * SP3 log: moon unit fixture deferred to integration capture — see PositionsMoonsIntegrationTest.
 */
beforeEach(function () {
    $exe = (string) config('swisseph.executable', '');

    if ($exe === '' || ! is_executable($exe)) {
        $this->markTestSkipped('swetest binary not available — moon integration test skipped.');
    }
});

it('returns a parsable position row for a planetary moon', function () {
    $frame = (new PositionsBuilder)
        ->setDateTime('2026-01-01 12:00:00', 'UTC')
        ->selectMoon(1)
        ->get();

    expect($frame->planet_bodies)->not->toBeEmpty();

    $body = $frame->planet_bodies[0];

    // Standard position-row shape: a body DTO + at least the default properties.
    expect($body['planet_body']->name)->not->toBe('');
    expect($body['properties'])->not->toBeEmpty();
})->group('integration');
