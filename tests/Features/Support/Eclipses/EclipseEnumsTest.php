<?php

use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;

it('exposes solar and lunar kinds', function () {
    expect(EclipseKind::SOLAR->value)->toBe('solar');
    expect(EclipseKind::LUNAR->value)->toBe('lunar');
});

it('maps swetest type words via fromToken', function () {
    expect(EclipseType::fromToken('annular'))->toBe(EclipseType::ANNULAR);
    expect(EclipseType::fromToken('total'))->toBe(EclipseType::TOTAL);
    expect(EclipseType::fromToken('partial'))->toBe(EclipseType::PARTIAL);
    expect(EclipseType::fromToken('penumbral'))->toBe(EclipseType::PENUMBRAL);
    expect(EclipseType::fromToken('anntot'))->toBe(EclipseType::HYBRID);
    expect(EclipseType::fromToken('hybrid'))->toBe(EclipseType::HYBRID);
    expect(EclipseType::fromToken('nope'))->toBeNull();
});

it('reports the known type tokens', function () {
    expect(EclipseType::isTypeToken('total'))->toBeTrue();
    expect(EclipseType::isTypeToken('saros'))->toBeFalse();
});

it('exposes global and local scopes', function () {
    expect(EclipseScope::GLOBAL->value)->toBe('global');
    expect(EclipseScope::LOCAL->value)->toBe('local');
});
