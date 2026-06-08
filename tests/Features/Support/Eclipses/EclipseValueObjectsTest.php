<?php

use DivineaLabs\Swisseph\Data\EclipseLocation;
use DivineaLabs\Swisseph\Data\EclipseMagnitudes;
use DivineaLabs\Swisseph\Data\SarosSeries;

it('holds three solar magnitudes', function () {
    $m = new EclipseMagnitudes(0.9638, 0.9797, 0.9288);
    expect($m->primary)->toBe(0.9638);
    expect($m->secondary)->toBe(0.9797);
    expect($m->tertiary)->toBe(0.9288);
});

it('holds two lunar magnitudes with null tertiary', function () {
    $m = new EclipseMagnitudes(1.1507, 2.1839, null);
    expect($m->primary)->toBe(1.1507);
    expect($m->secondary)->toBe(2.1839);
    expect($m->tertiary)->toBeNull();
});

it('holds a saros series and member', function () {
    $s = new SarosSeries(133, 27);
    expect($s->series)->toBe(133);
    expect($s->member)->toBe(27);
});

it('holds an eclipse location', function () {
    $l = new EclipseLocation(-170.6122, 6.4017);
    expect($l->longitude)->toBe(-170.6122);
    expect($l->latitude)->toBe(6.4017);
});
