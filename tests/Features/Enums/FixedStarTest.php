<?php

use DivineaLabs\Swisseph\Enums\FixedStar;

it('backs each fixed star with its Swiss Ephemeris catalog name', function () {
    expect(FixedStar::SIRIUS->value)->toBe('Sirius');
    expect(FixedStar::ALDEBARAN->value)->toBe('Aldebaran');
    expect(FixedStar::GALACTIC_CENTER->value)->toBe('Galactic Center');
});

it('keeps the catalog value for Rigil Kentaurus even though the display name differs', function () {
    expect(FixedStar::RIGIL_KENTAURUS->value)->toBe('Bungula');
    expect(FixedStar::RIGIL_KENTAURUS->getName())->toBe('Rigil Kentaurus');
});

it('uses the spelling Swiss Ephemeris expects for Zubeneshamali', function () {
    expect(FixedStar::ZUBENESHAMALI->value)->toBe('Zubeneshamali');
});

it('returns a non-empty value and display name for every case', function () {
    foreach (FixedStar::cases() as $star) {
        expect($star->value)->not->toBe('');
        expect($star->getName())->not->toBe('');
    }
});

it('round-trips through from()', function () {
    expect(FixedStar::from('Sirius'))->toBe(FixedStar::SIRIUS);
    expect(FixedStar::tryFrom('not-a-star'))->toBeNull();
});
