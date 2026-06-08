<?php

use DivineaLabs\Swisseph\Exceptions\EclipseKindNotSetException;
use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder;

it('exposes a fresh eclipses() sub-builder', function () {
    expect(Swisseph::eclipses())->toBeInstanceOf(EclipsesBuilder::class);
});

it('returns an isolated eclipses builder per call', function () {
    $a = Swisseph::eclipses()->solar()->count(9);
    $b = Swisseph::eclipses();

    // $b is fresh — building it without a kind throws, proving no shared state from $a
    expect(fn () => $b->build())
        ->toThrow(EclipseKindNotSetException::class);
});
