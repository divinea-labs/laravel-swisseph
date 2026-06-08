<?php

use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;
use DivineaLabs\Swisseph\Support\Rising\RisingsBuilder;

it('exposes fresh sub-builders per pipeline', function () {
    expect(Swisseph::positions())->toBeInstanceOf(PositionsBuilder::class);
    expect(Swisseph::risings())->toBeInstanceOf(RisingsBuilder::class);
});

it('returns an isolated builder on each positions() call (no shared mutable state)', function () {
    $a = Swisseph::positions()->setLocation(10.0, 20.0);
    $b = Swisseph::positions();

    expect($b->getLongitude())->not->toBe(10.0);
});
