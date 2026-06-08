<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Exceptions\OccultationTargetNotSetException;
use DivineaLabs\Swisseph\Facades\Swisseph;
use DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder;

it('exposes a fresh occultations() sub-builder', function () {
    expect(Swisseph::occultations())->toBeInstanceOf(OccultationsBuilder::class);
});

it('returns an isolated builder on each occultations() call', function () {
    $a = Swisseph::occultations()->forStar('Aldebaran');
    $b = Swisseph::occultations();

    // $b has no target set → build() must throw.
    expect(fn () => $b->build())
        ->toThrow(OccultationTargetNotSetException::class);
});
