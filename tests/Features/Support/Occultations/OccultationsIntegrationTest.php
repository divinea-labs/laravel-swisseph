<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Data\OccultationEventData;
use DivineaLabs\Swisseph\Facades\Swisseph;

function occultationsBinaryAvailable(): bool
{
    $exe = (string) config('swisseph.executable', '');

    return $exe !== '' && is_file($exe) && is_executable($exe);
}

beforeEach(function () {
    if (! occultationsBinaryAvailable()) {
        $this->markTestSkipped('swetest binary not available — integration test skipped.');
    }
});

it('returns a typed global occultation collection from the real binary', function () {
    $result = Swisseph::occultations()
        ->forStar('Aldebaran')
        ->global()
        ->from('2033-01-01')
        ->count(1)
        ->get();

    expect($result)->toBeInstanceOf(OccultationCollection::class);

    $first = $result->first();
    if ($first !== null) {
        expect($first)->toBeInstanceOf(OccultationEventData::class);
        expect($first->magnitude)->toBeFloat();
        expect($first->maxAt->timezoneName)->toBe('UTC');
    }
});

it('returns a typed local occultation collection from the real binary', function () {
    $result = Swisseph::occultations()
        ->forStar('Aldebaran')
        ->local(21.0, 52.2, 100.0)
        ->from('2034-01-01')
        ->count(1)
        ->get();

    expect($result)->toBeInstanceOf(OccultationCollection::class);

    $first = $result->first();
    if ($first !== null) {
        expect($first->isLocal())->toBeTrue();
        expect($first->coreShadowKm)->toBeNull();
    }
});
