<?php

use DivineaLabs\Swisseph\Data\EclipseEventData;
use DivineaLabs\Swisseph\Facades\Swisseph;

function swetestBinaryAvailable(): bool
{
    $exe = (string) config('swisseph.executable', '');

    return $exe !== '' && is_file($exe) && is_executable($exe);
}

it('computes real solar global eclipses end-to-end', function () {
    if (! swetestBinaryAvailable()) {
        $this->markTestSkipped('swetest binary not available');
    }

    $collection = Swisseph::eclipses()
        ->solar()
        ->global()
        ->from('2026-01-01')
        ->count(2)
        ->get();

    expect($collection->all())->not->toBeEmpty();

    $first = $collection->first();
    expect($first)->toBeInstanceOf(EclipseEventData::class);
    expect($first->isSolar())->toBeTrue();
    expect($first->saros->series)->toBeGreaterThan(0);
    expect($first->magnitudes->primary)->toBeGreaterThan(0.0);
});

it('computes real lunar global eclipses end-to-end', function () {
    if (! swetestBinaryAvailable()) {
        $this->markTestSkipped('swetest binary not available');
    }

    $collection = Swisseph::eclipses()
        ->lunar()
        ->global()
        ->from('2026-01-01')
        ->count(2)
        ->get();

    expect($collection->all())->not->toBeEmpty();
    expect($collection->first()->isLunar())->toBeTrue();
});
