<?php

use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;

it('strips the command-echo and geo header lines when skip prefixes are given', function () {
    // Use the PHP binary itself as a fake swetest that emits a swetest-like
    // block (echo line + geo header + data line). PHP_BINARY is guaranteed to
    // exist on every platform running the suite, unlike `/bin/sh`/`printf`
    // which are absent on Windows. Symfony Process escapes the argument
    // per-platform, so the single `-r` snippet works on both Unix and Windows.
    $executor = new SwissephExecutor;

    $lines = $executor->runRaw(
        [PHP_BINARY, '-r', 'echo "./swetests -edir./ephe\ngeo. long 21.0, lat 52.2, alt 100.0\npartial 12.08.2026\n";'],
        skipPrefixes: ['./swetests', 'geo. long'],
    );

    expect($lines)->toBe(['partial 12.08.2026']);
});
