<?php

declare(strict_types=1);

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationContacts;
use DivineaLabs\Swisseph\Data\OccultationEventData;
use DivineaLabs\Swisseph\Data\OccultationLocation;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;

function globalEvent(): OccultationEventData
{
    $start = Carbon::parse('2033-08-18 12:06:05', 'UTC');
    $end = Carbon::parse('2033-08-18 13:36:53.3', 'UTC');

    return new OccultationEventData(
        type: OccultationType::TOTAL,
        centrality: Centrality::NON_CENTRAL,
        scope: OccultationScope::GLOBAL,
        maxAt: Carbon::parse('2033-08-18 12:51:36', 'UTC'),
        julianDay: 2463828.035833,
        deltaT: 73.2,
        magnitude: 1.0,
        coreShadowKm: -3476.300002,
        contacts: new OccultationContacts($start, $start, $end, $end),
        location: new OccultationLocation(107.116667, 72.583333),
        duration: null,
    );
}

it('exposes scope and type accessors', function () {
    $event = globalEvent();

    expect($event->isLocal())->toBeFalse();
    expect($event->isTotal())->toBeTrue();
    expect($event->scope)->toBe(OccultationScope::GLOBAL);
    expect($event->centrality)->toBe(Centrality::NON_CENTRAL);
    expect($event->coreShadowKm)->toBe(-3476.300002);
    expect($event->location)->toBeInstanceOf(OccultationLocation::class);
    expect($event->duration)->toBeNull();
});

it('reports local scope and null centrality for a local event', function () {
    $start = Carbon::parse('2034-01-29 15:39:02.2', 'UTC');
    $end = Carbon::parse('2034-01-29 16:31:23.2', 'UTC');

    $event = new OccultationEventData(
        type: OccultationType::TOTAL,
        centrality: null,
        scope: OccultationScope::LOCAL,
        maxAt: Carbon::parse('2034-01-29 16:04:52.8', 'UTC'),
        julianDay: 2463992.170056,
        deltaT: 73.3,
        magnitude: 1.0,
        coreShadowKm: null,
        contacts: new OccultationContacts($start, $start, $end, $end),
        location: null,
        duration: 3141.03,
    );

    expect($event->isLocal())->toBeTrue();
    expect($event->isTotal())->toBeTrue();
    expect($event->centrality)->toBeNull();
    expect($event->coreShadowKm)->toBeNull();
    expect($event->location)->toBeNull();
    expect($event->duration)->toBe(3141.03);
});
