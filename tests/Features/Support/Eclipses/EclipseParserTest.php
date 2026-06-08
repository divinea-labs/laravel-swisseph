<?php

use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;
use DivineaLabs\Swisseph\Support\Eclipses\EclipseParser;

/**
 * Helper: split a fixture string into trimmed, non-empty lines,
 * matching what SwissephExecutor hands the parser (echo + geo header already stripped).
 */
function eclipseLines(string $fixture): array
{
    $lines = preg_split("/\R/u", trim($fixture)) ?: [];
    $lines = array_map('trim', $lines);

    return array_values(array_filter($lines, static fn ($l) => $l !== ''));
}

it('returns an empty collection for no lines', function () {
    $c = (new EclipseParser)->parse([], EclipseKind::SOLAR, EclipseScope::GLOBAL);
    expect($c->all())->toBe([]);
});

it('parses two solar global eclipses field-by-field', function () {
    // EXACT capture: SP1 "Solar, global (-solecl -n2)" — 3 lines/event.
    $fixture = <<<'TXT'
annular solar	17.02.2026	  12:11:51.1	131.068478 km	0.9638/0.9797/0.9288	saros 121/61	2461089.008230
	  09:56:44.8    11:43:01.9    12:41:02.0    14:27:37.3 dt=71.1
	  87° 4' 6"	 -64°41' 2"	2 min 19.42 sec
total solar	12.08.2026	  17:45:56.8	-132.445818 km	1.0395/1.0178/1.0806	saros 126/48	2461265.240240
	  15:34:30.7    16:58:06.9    18:33:58.2    19:57:55.9 dt=71.3
	 -25° 5'40"	  65° 9'55"	2 min 18.03 sec
TXT;

    $events = (new EclipseParser)
        ->parse(eclipseLines($fixture), EclipseKind::SOLAR, EclipseScope::GLOBAL)
        ->all();

    expect($events)->toHaveCount(2);

    $a = $events[0];
    expect($a->kind)->toBe(EclipseKind::SOLAR);
    expect($a->type)->toBe(EclipseType::ANNULAR);
    expect($a->scope)->toBe(EclipseScope::GLOBAL);
    expect($a->maxAt->format('Y-m-d H:i:s'))->toBe('2026-02-17 12:11:51');
    expect($a->julianDay)->toBe(2461089.008230);
    expect($a->coreShadowKm)->toBe(131.068478);
    expect($a->magnitudes->primary)->toBe(0.9638);
    expect($a->magnitudes->secondary)->toBe(0.9797);
    expect($a->magnitudes->tertiary)->toBe(0.9288);
    expect($a->saros->series)->toBe(121);
    expect($a->saros->member)->toBe(61);
    expect($a->deltaT)->toBe(71.1);
    // Solar maps 4 contacts onto partialStart/centralStart/centralEnd/partialEnd
    expect($a->contacts->penumbralStart)->toBeNull();
    expect($a->contacts->penumbralEnd)->toBeNull();
    expect($a->contacts->partialStart->format('H:i:s'))->toBe('09:56:44');
    expect($a->contacts->centralStart->format('H:i:s'))->toBe('11:43:01');
    expect($a->contacts->centralEnd->format('H:i:s'))->toBe('12:41:02');
    expect($a->contacts->partialEnd->format('H:i:s'))->toBe('14:27:37');
    // L3: central line lon/lat + duration "2 min 19.42 sec" → 139.42 s
    expect(round($a->location->longitude, 4))->toBe(round(87 + 4 / 60 + 6 / 3600, 4));
    expect(round($a->location->latitude, 4))->toBe(round(-(64 + 41 / 60 + 2 / 3600), 4));
    expect($a->duration)->toBe(139.42);

    $b = $events[1];
    expect($b->type)->toBe(EclipseType::TOTAL);
    expect($b->coreShadowKm)->toBe(-132.445818);
    expect($b->magnitudes->primary)->toBe(1.0395);
    expect($b->saros->series)->toBe(126);
    expect($b->saros->member)->toBe(48);
    expect($b->duration)->toBe(138.03);
    expect(round($b->location->latitude, 4))->toBe(round(65 + 9 / 60 + 55 / 3600, 4));
});

it('parses solar local eclipses with `-` placeholder contacts', function () {
    // EXACT capture: SP1 "Solar, local" — 2 lines/event (geo header already stripped).
    $fixture = <<<'TXT'
partial 12.08.2026	  18:03:06.0	0.8511/0.8511/0.8190	saros 126/48	2461265.252152
	0 min 0.00 sec	  17:14:40.3     -            -            -         dt=71.3
partial  2.08.2027	  09:22:21.3	0.4127/0.4127/0.3038	saros 136/38	2461619.890525
	0 min 0.00 sec	  08:26:19.3     -            -           10:19:20.8  dt=71.8
TXT;

    $events = (new EclipseParser)
        ->parse(eclipseLines($fixture), EclipseKind::SOLAR, EclipseScope::LOCAL)
        ->all();

    expect($events)->toHaveCount(2);

    $a = $events[0];
    expect($a->kind)->toBe(EclipseKind::SOLAR);
    expect($a->type)->toBe(EclipseType::PARTIAL);
    expect($a->scope)->toBe(EclipseScope::LOCAL);
    expect($a->maxAt->format('Y-m-d H:i:s'))->toBe('2026-08-12 18:03:06');
    expect($a->julianDay)->toBe(2461265.252152);
    expect($a->coreShadowKm)->toBeNull();      // no km column in local
    expect($a->magnitudes->primary)->toBe(0.8511);
    expect($a->magnitudes->secondary)->toBe(0.8511);
    expect($a->magnitudes->tertiary)->toBe(0.8190);
    expect($a->saros->series)->toBe(126);
    expect($a->saros->member)->toBe(48);
    expect($a->deltaT)->toBe(71.3);
    expect($a->duration)->toBe(0.0);            // "0 min 0.00 sec"
    // L2 contacts: partialStart visible, others `-` (null)
    expect($a->contacts->partialStart->format('H:i:s'))->toBe('17:14:40');
    expect($a->contacts->centralStart)->toBeNull();
    expect($a->contacts->centralEnd)->toBeNull();
    expect($a->contacts->partialEnd)->toBeNull();
    expect($a->location)->toBeNull();           // local has no L3 location

    $b = $events[1];
    expect($b->maxAt->format('Y-m-d H:i:s'))->toBe('2027-08-02 09:22:21');
    expect($b->deltaT)->toBe(71.8);
    expect($b->contacts->partialStart->format('H:i:s'))->toBe('08:26:19');
    expect($b->contacts->centralStart)->toBeNull();
    expect($b->contacts->centralEnd)->toBeNull();
    expect($b->contacts->partialEnd->format('H:i:s'))->toBe('10:19:20');
});

it('parses lunar global eclipses incl. a partial with `-` contacts', function () {
    // EXACT capture: SP1 "Lunar, global (-lunecl -n2)" — 3 lines/event.
    $fixture = <<<'TXT'
total lunar eclipse	 3.03.2026	  11:33:39.0	1.1507/2.1839	saros 133/27	2461102.981702
    08:44:21.8    09:50:02.9    11:04:30.1    12:02:49.3    13:17:15.5    14:23:05.3 dt=71.1
	-170°36'44"	   6°24' 6"
partial lunar eclipse	28.08.2026	  04:12:55.5	0.9299/1.9646	saros 138/29	2461280.675642
    01:23:55.2    02:33:50.3     -            -           05:52:00.3    07:01:47.1 dt=71.3
	 -63° 6'38"	  -9°18' 3"
TXT;

    $events = (new EclipseParser)
        ->parse(eclipseLines($fixture), EclipseKind::LUNAR, EclipseScope::GLOBAL)
        ->all();

    expect($events)->toHaveCount(2);

    $a = $events[0];
    expect($a->kind)->toBe(EclipseKind::LUNAR);
    expect($a->type)->toBe(EclipseType::TOTAL);
    expect($a->scope)->toBe(EclipseScope::GLOBAL);
    expect($a->maxAt->format('Y-m-d H:i:s'))->toBe('2026-03-03 11:33:39');
    expect($a->julianDay)->toBe(2461102.981702);
    expect($a->coreShadowKm)->toBeNull();
    expect($a->duration)->toBeNull();           // lunar has no duration
    expect($a->magnitudes->primary)->toBe(1.1507);   // umbral
    expect($a->magnitudes->secondary)->toBe(2.1839); // penumbral
    expect($a->magnitudes->tertiary)->toBeNull();
    expect($a->saros->series)->toBe(133);
    expect($a->saros->member)->toBe(27);
    expect($a->deltaT)->toBe(71.1);
    // L2: 6 contacts, all present
    expect($a->contacts->penumbralStart->format('H:i:s'))->toBe('08:44:21');
    expect($a->contacts->partialStart->format('H:i:s'))->toBe('09:50:02');
    expect($a->contacts->centralStart->format('H:i:s'))->toBe('11:04:30');
    expect($a->contacts->centralEnd->format('H:i:s'))->toBe('12:02:49');
    expect($a->contacts->partialEnd->format('H:i:s'))->toBe('13:17:15');
    expect($a->contacts->penumbralEnd->format('H:i:s'))->toBe('14:23:05');
    expect(round($a->location->longitude, 4))->toBe(round(-(170 + 36 / 60 + 44 / 3600), 4));
    expect(round($a->location->latitude, 4))->toBe(round(6 + 24 / 60 + 6 / 3600, 4));

    $b = $events[1];
    expect($b->type)->toBe(EclipseType::PARTIAL);
    expect($b->magnitudes->primary)->toBe(0.9299);
    expect($b->magnitudes->secondary)->toBe(1.9646);
    // partial lunar: total phase contacts are `-` (null)
    expect($b->contacts->penumbralStart->format('H:i:s'))->toBe('01:23:55');
    expect($b->contacts->partialStart->format('H:i:s'))->toBe('02:33:50');
    expect($b->contacts->centralStart)->toBeNull();
    expect($b->contacts->centralEnd)->toBeNull();
    expect($b->contacts->partialEnd->format('H:i:s'))->toBe('05:52:00');
    expect($b->contacts->penumbralEnd->format('H:i:s'))->toBe('07:01:47');
    expect(round($b->location->latitude, 4))->toBe(round(-(9 + 18 / 60 + 3 / 3600), 4));
});

it('skips a malformed record without throwing', function () {
    $fixture = <<<'TXT'
total solar	NOT-A-DATE	  17:45:56.8	-132.445818 km	1.0395/1.0178/1.0806	saros 126/48	2461265.240240
	  15:34:30.7    16:58:06.9    18:33:58.2    19:57:55.9 dt=71.3
	 -25° 5'40"	  65° 9'55"	2 min 18.03 sec
TXT;

    $c = (new EclipseParser)->parse(eclipseLines($fixture), EclipseKind::SOLAR, EclipseScope::GLOBAL);
    expect($c->all())->toBe([]);
});
