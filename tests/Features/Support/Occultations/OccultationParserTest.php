<?php

declare(strict_types=1);

use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;
use DivineaLabs\Swisseph\Support\Occultations\OccultationParser;

/**
 * Lines as the executor hands them to the parser: command-echo and the
 * `geo. long` header are already stripped, each line trimmed.
 */
function globalOccultationLines(): array
{
    return [
        "total   non-central 18.08.2033\t  12:51:36.0\t-3476.300002 km\t1.000000\t2463828.035833",
        '12:06:05.0    12:06:05.0    13:36:53.3    13:36:53.3 dt=73.2',
        "107° 7'\t  72°35'",
    ];
}

function localOccultationLines(): array
{
    return [
        "total            29.01.2034\t  16:04:52.8\t1.000000\t2463992.170056",
        "52 min 21.03 sec\t  15:39:02.2    15:39:02.2    16:31:23.2    16:31:23.2  dt=73.3",
    ];
}

it('parses a global occultation block field-by-field', function () {
    $parser = new OccultationParser;

    $collection = $parser->parse(globalOccultationLines(), OccultationScope::GLOBAL);

    expect($collection)->toBeInstanceOf(OccultationCollection::class);
    expect($collection->all())->toHaveCount(1);

    $event = $collection->first();

    expect($event->type)->toBe(OccultationType::TOTAL);
    expect($event->centrality)->toBe(Centrality::NON_CENTRAL);
    expect($event->scope)->toBe(OccultationScope::GLOBAL);
    expect($event->maxAt->format('Y-m-d H:i:s'))->toBe('2033-08-18 12:51:36');
    expect($event->julianDay)->toBe(2463828.035833);
    expect($event->deltaT)->toBe(73.2);
    expect($event->magnitude)->toBe(1.0);
    expect($event->coreShadowKm)->toBe(-3476.300002);

    expect($event->contacts->exteriorStart->format('H:i:s'))->toBe('12:06:05');
    expect($event->contacts->interiorStart->format('H:i:s'))->toBe('12:06:05');
    expect($event->contacts->interiorEnd->format('H:i:s'))->toBe('13:36:53');
    expect($event->contacts->exteriorEnd->format('H:i:s'))->toBe('13:36:53');

    expect($event->location)->not->toBeNull();
    expect(round($event->location->longitude, 4))->toBe(round(107 + 7 / 60, 4));
    expect(round($event->location->latitude, 4))->toBe(round(72 + 35 / 60, 4));

    expect($event->duration)->toBeNull();
});

it('parses a local occultation block field-by-field', function () {
    $parser = new OccultationParser;

    $collection = $parser->parse(localOccultationLines(), OccultationScope::LOCAL);

    expect($collection->all())->toHaveCount(1);

    $event = $collection->first();

    expect($event->type)->toBe(OccultationType::TOTAL);
    expect($event->centrality)->toBeNull();
    expect($event->scope)->toBe(OccultationScope::LOCAL);
    expect($event->maxAt->format('Y-m-d H:i:s'))->toBe('2034-01-29 16:04:52');
    expect($event->julianDay)->toBe(2463992.170056);
    expect($event->deltaT)->toBe(73.3);
    expect($event->magnitude)->toBe(1.0);
    expect($event->coreShadowKm)->toBeNull();

    // duration: 52 min 21.03 sec = 3141.03 s
    expect($event->duration)->toBe(3141.03);

    expect($event->contacts->exteriorStart->format('H:i:s'))->toBe('15:39:02');
    expect($event->contacts->exteriorEnd->format('H:i:s'))->toBe('16:31:23');

    expect($event->location)->toBeNull();
});

it('maps a `-` contact placeholder to null', function () {
    $parser = new OccultationParser;

    $lines = [
        "total            29.01.2034\t  16:04:52.8\t1.000000\t2463992.170056",
        "52 min 21.03 sec\t  15:39:02.2     -            16:31:23.2    16:31:23.2  dt=73.3",
    ];

    $event = $parser->parse($lines, OccultationScope::LOCAL)->first();

    expect($event->contacts->exteriorStart->format('H:i:s'))->toBe('15:39:02');
    expect($event->contacts->interiorStart)->toBeNull();
    expect($event->contacts->interiorEnd->format('H:i:s'))->toBe('16:31:23');
});

it('returns an empty collection for no input', function () {
    $parser = new OccultationParser;

    expect($parser->parse([], OccultationScope::GLOBAL)->all())->toBe([]);
});

it('skips a malformed record without throwing', function () {
    $parser = new OccultationParser;

    $lines = [
        'garbage line that is not a type word',
        'another junk line',
    ];

    expect($parser->parse($lines, OccultationScope::GLOBAL)->all())->toBe([]);
});
