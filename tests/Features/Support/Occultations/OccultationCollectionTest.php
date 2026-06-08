<?php

declare(strict_types=1);

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Data\OccultationContacts;
use DivineaLabs\Swisseph\Data\OccultationEventData;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;

function sampleEvent(): OccultationEventData
{
    return new OccultationEventData(
        type: OccultationType::TOTAL,
        centrality: Centrality::CENTRAL,
        scope: OccultationScope::GLOBAL,
        maxAt: Carbon::parse('2033-08-18 12:51:36', 'UTC'),
        julianDay: 2463828.035833,
        deltaT: 73.2,
        magnitude: 1.0,
        coreShadowKm: -3476.3,
        contacts: new OccultationContacts(null, null, null, null),
        location: null,
        duration: null,
    );
}

it('exposes all() and first()', function () {
    $a = sampleEvent();
    $b = sampleEvent();

    $collection = new OccultationCollection([$a, $b]);

    expect($collection->all())->toBe([$a, $b]);
    expect($collection->first())->toBe($a);
});

it('returns null first() when empty', function () {
    $collection = new OccultationCollection([]);

    expect($collection->all())->toBe([]);
    expect($collection->first())->toBeNull();
});
