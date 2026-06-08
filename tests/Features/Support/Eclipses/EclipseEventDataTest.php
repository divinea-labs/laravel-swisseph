<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseContacts;
use DivineaLabs\Swisseph\Data\EclipseEventData;
use DivineaLabs\Swisseph\Data\EclipseLocation;
use DivineaLabs\Swisseph\Data\EclipseMagnitudes;
use DivineaLabs\Swisseph\Data\SarosSeries;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;

function makeSolarGlobalEvent(): EclipseEventData
{
    return new EclipseEventData(
        kind: EclipseKind::SOLAR,
        type: EclipseType::TOTAL,
        scope: EclipseScope::GLOBAL,
        maxAt: Carbon::parse('2026-08-12 17:45:56', 'UTC'),
        julianDay: 2461265.240240,
        deltaT: 71.3,
        magnitudes: new EclipseMagnitudes(1.0395, 1.0178, 1.0806),
        saros: new SarosSeries(126, 48),
        contacts: new EclipseContacts(null, null, null, null, null, null),
        location: new EclipseLocation(-25.0944, 65.1652),
        coreShadowKm: -132.445818,
        duration: 138.03,
    );
}

it('reports solar / total / global accessors', function () {
    $e = makeSolarGlobalEvent();
    expect($e->isSolar())->toBeTrue();
    expect($e->isLunar())->toBeFalse();
    expect($e->isTotal())->toBeTrue();
    expect($e->isLocal())->toBeFalse();
});

it('reports lunar and local accessors', function () {
    $e = new EclipseEventData(
        kind: EclipseKind::LUNAR,
        type: EclipseType::PARTIAL,
        scope: EclipseScope::LOCAL,
        maxAt: Carbon::parse('2026-08-28 04:12:55', 'UTC'),
        julianDay: 2461280.675642,
        deltaT: 71.3,
        magnitudes: new EclipseMagnitudes(0.9299, 1.9646, null),
        saros: new SarosSeries(138, 29),
        contacts: new EclipseContacts(null, null, null, null, null, null),
        location: null,
        coreShadowKm: null,
        duration: null,
    );

    expect($e->isLunar())->toBeTrue();
    expect($e->isSolar())->toBeFalse();
    expect($e->isTotal())->toBeFalse();
    expect($e->isLocal())->toBeTrue();
});
