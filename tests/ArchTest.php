<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('never uses the backtick shell-execution operator in the source', function () {
    // Found in PlanetBody descriptions: prose in backticks is a shell command in PHP, not a string.
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../src', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
            if ($token === '`') {
                $offenders[] = $file->getFilename();
                break;
            }
        }
    }

    expect($offenders)->toBe([]);
});
