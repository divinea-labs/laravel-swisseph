<?php

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseContacts;

it('holds six nullable lunar contacts', function () {
    $c = new EclipseContacts(
        penumbralStart: Carbon::parse('2026-03-03 08:44:21', 'UTC'),
        partialStart: Carbon::parse('2026-03-03 09:50:02', 'UTC'),
        centralStart: Carbon::parse('2026-03-03 11:04:30', 'UTC'),
        centralEnd: Carbon::parse('2026-03-03 12:02:49', 'UTC'),
        partialEnd: Carbon::parse('2026-03-03 13:17:15', 'UTC'),
        penumbralEnd: Carbon::parse('2026-03-03 14:23:05', 'UTC'),
    );

    expect($c->penumbralStart->format('H:i:s'))->toBe('08:44:21');
    expect($c->penumbralEnd->format('H:i:s'))->toBe('14:23:05');
});

it('leaves inapplicable contacts null (solar maps four)', function () {
    $c = new EclipseContacts(
        penumbralStart: null,
        partialStart: Carbon::parse('2026-02-17 09:56:44', 'UTC'),
        centralStart: Carbon::parse('2026-02-17 11:43:01', 'UTC'),
        centralEnd: Carbon::parse('2026-02-17 12:41:02', 'UTC'),
        partialEnd: Carbon::parse('2026-02-17 14:27:37', 'UTC'),
        penumbralEnd: null,
    );

    expect($c->penumbralStart)->toBeNull();
    expect($c->penumbralEnd)->toBeNull();
    expect($c->partialStart->format('H:i:s'))->toBe('09:56:44');
});
