<?php

declare(strict_types=1);

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationContacts;
use DivineaLabs\Swisseph\Data\OccultationLocation;

it('holds the four captured contacts with nullables', function () {
    $start = Carbon::parse('2033-08-18 12:06:05', 'UTC');
    $end = Carbon::parse('2033-08-18 13:36:53', 'UTC');

    $contacts = new OccultationContacts(
        exteriorStart: $start,
        interiorStart: $start,
        interiorEnd: $end,
        exteriorEnd: $end,
    );

    expect($contacts->exteriorStart)->toEqual($start);
    expect($contacts->interiorStart)->toEqual($start);
    expect($contacts->interiorEnd)->toEqual($end);
    expect($contacts->exteriorEnd)->toEqual($end);
});

it('allows null contacts', function () {
    $contacts = new OccultationContacts(null, null, null, null);

    expect($contacts->exteriorStart)->toBeNull();
    expect($contacts->exteriorEnd)->toBeNull();
});

it('holds a decimal-degree location', function () {
    $loc = new OccultationLocation(longitude: 107.116667, latitude: 72.583333);

    expect($loc->longitude)->toBe(107.116667);
    expect($loc->latitude)->toBe(72.583333);
});
