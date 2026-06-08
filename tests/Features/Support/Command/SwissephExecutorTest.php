<?php

use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;

it('strips the command-echo and geo header lines when skip prefixes are given', function () {
    // Use `printf` as a fake binary that emits a swetest-like block.
    $command = new SwissephCommand(
        executable: 'printf',
        arguments: [], // not used by toProcessArray below
    );

    // Build a command that prints an echo line, a geo header, and a data line.
    $cmd = new SwissephCommand(
        executable: '/bin/sh',
        arguments: [],
    );

    $executor = new SwissephExecutor;

    $lines = $executor->runRaw(
        ['/bin/sh', '-c', "printf '%s\\n' './swetests -edir./ephe' 'geo. long 21.0, lat 52.2, alt 100.0' 'partial 12.08.2026'"],
        skipPrefixes: ['./swetests', 'geo. long'],
    );

    expect($lines)->toBe(['partial 12.08.2026']);
});
