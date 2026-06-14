<?php

use DivineaLabs\Swisseph\Enums\FixedStar;
use DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder;
use DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder;

it('accepts a FixedStar enum in OccultationsBuilder::forStar', function () {
    $builder = (new OccultationsBuilder)->forStar(FixedStar::SIRIUS);

    expect($builder)->toBeInstanceOf(OccultationsBuilder::class);
});

it('accepts a FixedStar enum in HeliacalBuilder::forStar', function () {
    $builder = (new HeliacalBuilder)->forStar(FixedStar::ALDEBARAN);

    expect($builder)->toBeInstanceOf(HeliacalBuilder::class);
});
