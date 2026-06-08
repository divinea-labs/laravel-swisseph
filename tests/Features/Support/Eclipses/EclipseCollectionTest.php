<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseCollection;
use DivineaLabs\Swisseph\Data\EclipseContacts;
use DivineaLabs\Swisseph\Data\EclipseEventData;
use DivineaLabs\Swisseph\Data\EclipseMagnitudes;
use DivineaLabs\Swisseph\Data\SarosSeries;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;

function makeEvent(EclipseKind $kind): EclipseEventData
{
    return new EclipseEventData(
        kind: $kind,
        type: EclipseType::TOTAL,
        scope: EclipseScope::GLOBAL,
        maxAt: Carbon::parse('2026-01-01 00:00:00', 'UTC'),
        julianDay: 2461000.0,
        deltaT: 71.0,
        magnitudes: new EclipseMagnitudes(1.0, 1.0, null),
        saros: new SarosSeries(1, 1),
        contacts: new EclipseContacts(null, null, null, null, null, null),
        location: null,
        coreShadowKm: null,
        duration: null,
    );
}

it('returns all events and the first', function () {
    $solar = makeEvent(EclipseKind::SOLAR);
    $lunar = makeEvent(EclipseKind::LUNAR);
    $c = new EclipseCollection([$solar, $lunar]);

    expect($c->all())->toBe([$solar, $lunar]);
    expect($c->first())->toBe($solar);
});

it('filters by kind', function () {
    $solar = makeEvent(EclipseKind::SOLAR);
    $lunar = makeEvent(EclipseKind::LUNAR);
    $c = new EclipseCollection([$solar, $lunar]);

    expect($c->solarOnly())->toBe([$solar]);
    expect($c->lunarOnly())->toBe([$lunar]);
});

it('returns null first on empty collection', function () {
    $c = new EclipseCollection([]);
    expect($c->all())->toBe([]);
    expect($c->first())->toBeNull();
});
