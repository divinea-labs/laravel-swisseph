<?php

use DivineaLabs\Swisseph\Enums\Asteroid;

it('backs each asteroid with its MPC number', function () {
    expect(Asteroid::EROS->value)->toBe(433);
    expect(Asteroid::ERIS->value)->toBe(136199);
    expect(Asteroid::VARUNA->value)->toBe(20000);
});

it('returns a non-empty name for every case', function () {
    foreach (Asteroid::cases() as $asteroid) {
        expect($asteroid->getName())->not->toBe('');
    }
});

it('exposes a positive MPC number for every case', function () {
    foreach (Asteroid::cases() as $asteroid) {
        expect($asteroid->value)->toBeGreaterThan(0);
    }
});

it('maps a case to its asteroid name', function () {
    expect(Asteroid::EROS->getName())->toBe('Eros');
    expect(Asteroid::QUAOAR->getName())->toBe('Quaoar');
});

it('round-trips through from()', function () {
    expect(Asteroid::from(433))->toBe(Asteroid::EROS);
    expect(Asteroid::tryFrom(999999))->toBeNull();
});
