<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Enums\PlanetBody;

it('gives every case a slug', function () {
    foreach (PlanetBody::cases() as $case) {
        expect($case->slug())->toMatch('/^[a-z][a-z0-9_]*$/');
    }
});

it('keeps slugs unique across all cases', function () {
    $slugs = array_map(fn (PlanetBody $c): string => $c->slug(), PlanetBody::cases());

    expect($slugs)->toHaveCount(count(array_unique($slugs)));
});

it('round-trips a display name back to the same case', function () {
    foreach (PlanetBody::cases() as $case) {
        expect(PlanetBody::fromName($case->getName()))->toBe($case);
    }
});

it('returns null for a name it does not know', function () {
    expect(PlanetBody::fromName('Ascendant'))->toBeNull()
        ->and(PlanetBody::fromName(''))->toBeNull();
});

it('names the bodies the API actually exposes', function () {
    expect(PlanetBody::SUN->slug())->toBe('sun')
        ->and(PlanetBody::MEAN_APOG->slug())->toBe('mean_apogee')
        ->and(PlanetBody::TRUE_NODE->slug())->toBe('true_node')
        ->and(PlanetBody::MEAN_NODE->slug())->toBe('mean_node');
});
