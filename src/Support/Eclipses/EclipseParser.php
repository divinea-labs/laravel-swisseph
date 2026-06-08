<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Eclipses;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseCollection;
use DivineaLabs\Swisseph\Data\EclipseContacts;
use DivineaLabs\Swisseph\Data\EclipseEventData;
use DivineaLabs\Swisseph\Data\EclipseLocation;
use DivineaLabs\Swisseph\Data\EclipseMagnitudes;
use DivineaLabs\Swisseph\Data\SarosSeries;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Enums\EclipseType;

final class EclipseParser
{
    /**
     * Parse swetest eclipse output into a collection.
     *
     * Contract: never throws — malformed records are skipped.
     *
     * @param  array<int, string>  $lines  stdout lines, echo + geo header already stripped
     */
    public function parse(array $lines, EclipseKind $kind, EclipseScope $scope): EclipseCollection
    {
        $linesPerRecord = $this->linesPerRecord($kind, $scope);

        // Group into records, each starting at a known type-word line.
        $records = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $firstToken = $this->firstToken($line);

            if (EclipseType::isTypeToken($firstToken)) {
                if (is_array($current)) {
                    $records[] = $current;
                }
                $current = [$line];

                continue;
            }

            if (is_array($current)) {
                $current[] = $line;
            }
        }

        if (is_array($current)) {
            $records[] = $current;
        }

        $events = [];

        foreach ($records as $record) {
            if (count($record) < $linesPerRecord) {
                continue; // incomplete block — skip tolerantly
            }

            $event = $this->parseRecord($record, $kind, $scope);
            if ($event instanceof EclipseEventData) {
                $events[] = $event;
            }
        }

        return new EclipseCollection($events);
    }

    private function linesPerRecord(EclipseKind $kind, EclipseScope $scope): int
    {
        if ($kind === EclipseKind::SOLAR && $scope === EclipseScope::LOCAL) {
            return 2;
        }

        return 3; // solar-global, lunar-global
    }

    /**
     * @param  array<int, string>  $record
     */
    private function parseRecord(array $record, EclipseKind $kind, EclipseScope $scope): ?EclipseEventData
    {
        if ($kind === EclipseKind::SOLAR) {
            return $scope === EclipseScope::LOCAL
                ? $this->parseSolarLocal($record)
                : $this->parseSolarGlobal($record);
        }

        return $this->parseLunarGlobal($record);
    }

    /**
     * Solar global, 3 lines:
     * L1: <type> solar  date  maxTime  <km> km  m1/m2/m3  saros s/n  JD
     * L2: c1 c2 c3 c4 dt=<x>
     * L3: lon  lat  <n> min <s> sec
     *
     * @param  array<int, string>  $record
     */
    private function parseSolarGlobal(array $record): ?EclipseEventData
    {
        $l1 = $this->tokens($record[0]);

        // l1: [type, 'solar', date, time, km, 'km', m1/m2/m3, 'saros', s/n, JD]
        $type = EclipseType::fromToken($l1[0] ?? '');
        if ($type === null) {
            return null;
        }

        $date = $l1[2] ?? '';
        $maxAt = $this->dateTime($date, $l1[3] ?? '');
        if ($maxAt === null) {
            return null;
        }

        $coreShadowKm = $this->floatOrNull($l1[4] ?? null);
        $magnitudes = $this->magnitudes($l1[6] ?? '');
        $saros = $this->saros($l1[8] ?? '');
        $julianDay = (float) ($l1[9] ?? 0.0);

        $l2 = $this->tokens($record[1]);
        $contacts = new EclipseContacts(
            penumbralStart: null,
            partialStart: $this->dateTime($date, $l2[0] ?? '-'),
            centralStart: $this->dateTime($date, $l2[1] ?? '-'),
            centralEnd: $this->dateTime($date, $l2[2] ?? '-'),
            partialEnd: $this->dateTime($date, $l2[3] ?? '-'),
            penumbralEnd: null,
        );
        $deltaT = $this->deltaT($record[1]);

        $location = $this->location($this->tokens($record[2]));
        $duration = $this->duration($record[2]);

        return new EclipseEventData(
            kind: EclipseKind::SOLAR,
            type: $type,
            scope: EclipseScope::GLOBAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT,
            magnitudes: $magnitudes,
            saros: $saros,
            contacts: $contacts,
            location: $location,
            coreShadowKm: $coreShadowKm,
            duration: $duration,
        );
    }

    /**
     * Solar local, 2 lines:
     * L1: <type>  date  maxTime  m1/m2/m3  saros s/n  JD   (no km)
     * L2: <n> min <s> sec  c1 c2 c3 c4  dt=<x>   (`-` where not visible)
     *
     * @param  array<int, string>  $record
     */
    private function parseSolarLocal(array $record): ?EclipseEventData
    {
        $l1 = $this->tokens($record[0]);

        // l1: [type, date, time, m1/m2/m3, 'saros', s/n, JD]
        $type = EclipseType::fromToken($l1[0] ?? '');
        if ($type === null) {
            return null;
        }

        $date = $l1[1] ?? '';
        $maxAt = $this->dateTime($date, $l1[2] ?? '');
        if ($maxAt === null) {
            return null;
        }

        $magnitudes = $this->magnitudes($l1[3] ?? '');
        $saros = $this->saros($l1[5] ?? '');
        $julianDay = (float) ($l1[6] ?? 0.0);

        // L2 begins with "N min S.s sec" (4 tokens) then 4 contacts then dt=
        $l2 = $this->tokens($record[1]);
        $duration = $this->duration($record[1]);

        // Drop the duration tokens ("N", "min", "S.s", "sec") to find contacts.
        $contactTokens = $this->stripDurationTokens($l2);

        $contacts = new EclipseContacts(
            penumbralStart: null,
            partialStart: $this->dateTime($date, $contactTokens[0] ?? '-'),
            centralStart: $this->dateTime($date, $contactTokens[1] ?? '-'),
            centralEnd: $this->dateTime($date, $contactTokens[2] ?? '-'),
            partialEnd: $this->dateTime($date, $contactTokens[3] ?? '-'),
            penumbralEnd: null,
        );
        $deltaT = $this->deltaT($record[1]);

        return new EclipseEventData(
            kind: EclipseKind::SOLAR,
            type: $type,
            scope: EclipseScope::LOCAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT,
            magnitudes: $magnitudes,
            saros: $saros,
            contacts: $contacts,
            location: null,
            coreShadowKm: null,
            duration: $duration,
        );
    }

    /**
     * Lunar global, 3 lines:
     * L1: <type> lunar eclipse  date  maxTime  umbral/penumbral  saros s/n  JD
     * L2: c1 c2 c3 c4 c5 c6 dt=<x>   (`-` where absent)
     * L3: lon  lat
     *
     * @param  array<int, string>  $record
     */
    private function parseLunarGlobal(array $record): ?EclipseEventData
    {
        $l1 = $this->tokens($record[0]);

        // l1: [type, 'lunar', 'eclipse', date, time, mag/mag, 'saros', s/n, JD]
        $type = EclipseType::fromToken($l1[0] ?? '');
        if ($type === null) {
            return null;
        }

        $date = $l1[3] ?? '';
        $maxAt = $this->dateTime($date, $l1[4] ?? '');
        if ($maxAt === null) {
            return null;
        }

        $magnitudes = $this->magnitudes($l1[5] ?? '');
        $saros = $this->saros($l1[7] ?? '');
        $julianDay = (float) ($l1[8] ?? 0.0);

        $l2 = $this->tokens($record[1]);
        $contacts = new EclipseContacts(
            penumbralStart: $this->dateTime($date, $l2[0] ?? '-'),
            partialStart: $this->dateTime($date, $l2[1] ?? '-'),
            centralStart: $this->dateTime($date, $l2[2] ?? '-'),
            centralEnd: $this->dateTime($date, $l2[3] ?? '-'),
            partialEnd: $this->dateTime($date, $l2[4] ?? '-'),
            penumbralEnd: $this->dateTime($date, $l2[5] ?? '-'),
        );
        $deltaT = $this->deltaT($record[1]);

        $location = $this->location($this->tokens($record[2]));

        return new EclipseEventData(
            kind: EclipseKind::LUNAR,
            type: $type,
            scope: EclipseScope::GLOBAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT,
            magnitudes: $magnitudes,
            saros: $saros,
            contacts: $contacts,
            location: $location,
            coreShadowKm: null,
            duration: null,
        );
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $line): array
    {
        $parts = preg_split('/\s+/', trim($line)) ?: [];

        return array_values(array_filter($parts, static fn ($t) => $t !== ''));
    }

    private function firstToken(string $line): string
    {
        return $this->tokens($line)[0] ?? '';
    }

    /**
     * Build a UTC Carbon from a d.m.Y date and HH:MM:SS[.f] time.
     * `-` (or any non-time token) → null.
     */
    private function dateTime(string $date, string $time): ?Carbon
    {
        $time = trim($time);
        $date = trim($date);

        if ($time === '' || $time === '-') {
            return null;
        }
        if (! preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $date)) {
            return null;
        }
        if (! preg_match('/^\d{1,2}:\d{2}:\d{2}(\.\d+)?$/', $time)) {
            return null;
        }

        $parts = explode('.', $time, 2);
        $hms = $parts[0];
        $fracRaw = $parts[1] ?? '0';
        $micros = (int) str_pad(substr($fracRaw, 0, 6), 6, '0', STR_PAD_RIGHT);

        $dt = Carbon::createFromFormat('d.m.Y H:i:s', "{$date} {$hms}", 'UTC');
        if (! ($dt instanceof Carbon)) {
            return null;
        }

        return $dt->setMicroseconds($micros);
    }

    /**
     * Split `m1/m2/m3` (solar) or `m1/m2` (lunar) into magnitudes.
     */
    private function magnitudes(string $token): EclipseMagnitudes
    {
        $parts = explode('/', trim($token));

        return new EclipseMagnitudes(
            primary: (float) ($parts[0] ?? 0.0),
            secondary: (float) ($parts[1] ?? 0.0),
            tertiary: isset($parts[2]) ? (float) $parts[2] : null,
        );
    }

    /**
     * Parse the `s/n` token following the literal `saros` (e.g. `121/61`).
     */
    private function saros(string $token): SarosSeries
    {
        $parts = explode('/', trim($token));

        return new SarosSeries(
            series: (int) ($parts[0] ?? 0),
            member: (int) ($parts[1] ?? 0),
        );
    }

    /**
     * Extract `dt=<x>` from a raw line.
     */
    private function deltaT(string $line): float
    {
        if (preg_match('/dt=([0-9.]+)/', $line, $m) === 1) {
            return (float) $m[1];
        }

        return 0.0;
    }

    /**
     * Parse a `<n> min <s> sec` substring → total seconds.
     * Result is rounded to 2 decimal places to avoid floating-point drift.
     */
    private function duration(string $line): ?float
    {
        if (preg_match('/(\d+)\s*min\s*([0-9.]+)\s*sec/', $line, $m) === 1) {
            return round(((int) $m[1]) * 60 + (float) $m[2], 2);
        }

        return null;
    }

    /**
     * Convert two degree token groups (`<deg>°<min>'<sec>"`) → EclipseLocation.
     * Because tokenizing on \s+ may split inside a coordinate (e.g. `87° 4' 6"`),
     * this works on the raw degree substrings re-joined from all tokens.
     *
     * @param  array<int, string>  $tokens
     */
    private function location(array $tokens): ?EclipseLocation
    {
        // Re-join everything and pull the first two coordinate groups.
        $rest = implode(' ', $tokens);

        // A coordinate group: optional sign, degrees, °, minutes, ', seconds, "
        $pattern = '/(-?\s*\d+)\s*°\s*(\d+)\s*\'\s*(\d+)\s*"/';
        if (preg_match_all($pattern, $rest, $matches, PREG_SET_ORDER) < 2) {
            return null;
        }

        $lon = $this->dms($matches[0]);
        $lat = $this->dms($matches[1]);

        return new EclipseLocation(longitude: $lon, latitude: $lat);
    }

    /**
     * @param  array<int, string>  $m  [full, deg(with optional sign), min, sec]
     */
    private function dms(array $m): float
    {
        $degToken = str_replace(' ', '', $m[1]);
        $negative = str_starts_with($degToken, '-');
        $deg = abs((int) $degToken);
        $min = (int) $m[2];
        $sec = (int) $m[3];

        $value = $deg + $min / 60 + $sec / 3600;

        return $negative ? -$value : $value;
    }

    /**
     * Drop the leading `N min S.s sec` tokens from a local L2 token list,
     * leaving the contact tokens.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function stripDurationTokens(array $tokens): array
    {
        // Find the 'sec' token; contacts start right after it.
        foreach ($tokens as $i => $t) {
            if ($t === 'sec') {
                return array_values(array_slice($tokens, $i + 1));
            }
        }

        return $tokens;
    }

    private function floatOrNull(?string $token): ?float
    {
        if ($token === null) {
            return null;
        }
        $token = trim($token);
        if ($token === '' || $token === '-') {
            return null;
        }
        if (! is_numeric($token)) {
            return null;
        }

        return (float) $token;
    }
}
