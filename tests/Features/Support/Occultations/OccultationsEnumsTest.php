<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;

it('backs occultation types with swetest type words', function () {
    expect(OccultationType::TOTAL->value)->toBe('total');
    expect(OccultationType::ANNULAR->value)->toBe('annular');
    expect(OccultationType::PARTIAL->value)->toBe('partial');
    expect(OccultationType::from('total'))->toBe(OccultationType::TOTAL);
});

it('backs centrality with swetest qualifier words', function () {
    expect(Centrality::CENTRAL->value)->toBe('central');
    expect(Centrality::NON_CENTRAL->value)->toBe('non-central');
    expect(Centrality::from('non-central'))->toBe(Centrality::NON_CENTRAL);
});

it('backs scope with global/local', function () {
    expect(OccultationScope::GLOBAL->value)->toBe('global');
    expect(OccultationScope::LOCAL->value)->toBe('local');
});
