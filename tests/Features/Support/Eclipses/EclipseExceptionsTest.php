<?php

use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Exceptions\EclipseKindNotSetException;
use DivineaLabs\Swisseph\Exceptions\InvalidEclipseFilterException;

it('builds a kind-not-set exception', function () {
    $e = EclipseKindNotSetException::missing();
    expect($e)->toBeInstanceOf(InvalidArgumentException::class);
    expect($e->getMessage())->toContain('solar()');
});

it('builds an invalid-filter exception naming the filter and kind', function () {
    $e = InvalidEclipseFilterException::notAllowed('onlyAnnular', EclipseKind::LUNAR);
    expect($e)->toBeInstanceOf(InvalidArgumentException::class);
    expect($e->getMessage())->toContain('onlyAnnular');
    expect($e->getMessage())->toContain('lunar');
});
