<?php

declare(strict_types=1);

function fixtureContent(string $name): string
{
    $path = __DIR__.'/Fixtures/'.$name;

    if (! file_exists($path)) {
        throw new RuntimeException("Fixture not found: {$path}");
    }

    $content = file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException("Unable to read fixture: {$path}");
    }

    return $content;
}

function fixtureLines(string $name): array
{
    $content = fixtureContent($name);

    return array_values(array_filter(
        array_map(
            static fn (string $line) => rtrim($line, "\r\n"),
            preg_split("/\r\n|\n|\r/", $content) ?: []
        ),
        static fn (string $line) => $line !== ''
    ));
}
