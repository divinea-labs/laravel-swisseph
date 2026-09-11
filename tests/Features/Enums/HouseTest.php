<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Data\HouseData;
use DivineaLabs\Swisseph\Enums\House;
use DivineaLabs\Swisseph\Enums\HouseSystems;
use DivineaLabs\Swisseph\Support\Positions\PositionsBuilder;
use DivineaLabs\Swisseph\Support\Positions\PositionsParser;

/** @return list<array{house: HouseData, properties: array}> */
function kochHouseRows(): array
{
    $builder = new PositionsBuilder;
    $builder->setLocation(17.038538, 51.107883, 'Wroclaw');
    $builder->withHouses(HouseSystems::KOCH);

    return (new PositionsParser)->parse(fixtureLines('swetest-koch.txt'), $builder)->houses;
}

it('numbers the twelve cusps and nothing else', function () {
    $numbers = array_map(
        fn (array $row): ?int => $row['house']->house()->cuspNumber(),
        kochHouseRows(),
    );

    // Swiss Ephemeris numbers the points 13..20 in the same column as the cusps -
    // exactly why a consumer must not read the raw index as a house number.
    expect($numbers)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, null, null, null, null, null, null, null, null]);
});

it('resolves every parsed row to the case whose name it carries', function () {
    foreach (kochHouseRows() as $row) {
        expect($row['house']->house()->getName())->toBe($row['house']->name);
    }
});

it('gives every case a unique wire-safe slug', function () {
    $slugs = array_map(fn (House $c): string => $c->slug(), House::cases());

    foreach ($slugs as $slug) {
        expect($slug)->toMatch('/^[a-z][a-z0-9_]*$/');
    }
    expect($slugs)->toHaveCount(count(array_unique($slugs)));
});

it('names the points with the slugs consumers already publish', function () {
    expect(House::HOUSE_1->slug())->toBe('house_1')
        ->and(House::HOUSE_12->slug())->toBe('house_12')
        ->and(House::ASCENDANT->slug())->toBe('ascendant')
        ->and(House::MC->slug())->toBe('midheaven')
        ->and(House::ARMC->slug())->toBe('armc')
        ->and(House::VERTEX->slug())->toBe('vertex')
        ->and(House::EQUAT_ASC->slug())->toBe('equatorial_ascendant')
        ->and(House::CO_ASC_KOCH->slug())->toBe('co_ascendant_koch')
        ->and(House::CO_ASC_MUNKASEY->slug())->toBe('co_ascendant_munkasey')
        ->and(House::POLAR_ASC_MUNKASEY->slug())->toBe('polar_ascendant');
});

it('round-trips a display name back to the same case', function () {
    foreach (House::cases() as $case) {
        expect(House::fromName($case->getName()))->toBe($case);
    }
});

it('returns null for a name it does not know', function () {
    // '1' is the name an earlier consumer assumed the cusps carry. They do not.
    expect(House::fromName('1'))->toBeNull()
        ->and(House::fromName('House 13'))->toBeNull()
        ->and(House::fromName(''))->toBeNull();
});

it('keeps display names free of stray quote characters', function () {
    foreach (House::cases() as $case) {
        expect($case->getName())->not->toContain('"');
    }
});
