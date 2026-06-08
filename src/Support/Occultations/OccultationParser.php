<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Occultations;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Data\OccultationContacts;
use DivineaLabs\Swisseph\Data\OccultationEventData;
use DivineaLabs\Swisseph\Data\OccultationLocation;
use DivineaLabs\Swisseph\Enums\Centrality;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\OccultationType;

/**
 * Block-aware parser for swetest `-occult` output.
 *
 * Contract: never throws — malformed records are skipped tolerantly.
 *
 * Global format (3 lines/event, after echo stripped):
 *   L1: <type> [central|non-central] <d.m.Y> <H:i:s.u> <km> km <magnitude> <JD>
 *   L2: <c1> <c2> <c3> <c4> dt=<deltaT>
 *   L3: <lon°min'> <lat°min'>
 *
 * Local format (2 lines/event, after echo + geo. long header stripped):
 *   L1: <type> <d.m.Y> <H:i:s.u> <magnitude> <JD>
 *   L2: <N> min <S> sec <c1> <c2> <c3> <c4> dt=<deltaT>
 */
final class OccultationParser
{
    /** The swetest type-word tokens that begin a record. */
    private const TYPE_WORDS = ['total', 'annular', 'partial'];

    /**
     * @param  array<int, string>  $lines  stdout lines, echo + geo header already stripped
     */
    public function parse(array $lines, OccultationScope $scope): OccultationCollection
    {
        $blockSize = $scope === OccultationScope::GLOBAL ? 3 : 2;
        $events = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $trimmed = trim($lines[$i]);
            if ($trimmed === '') {
                continue;
            }

            $firstToken = $this->firstToken($trimmed);

            // Only advance a block when the line starts with a known type word.
            if (! in_array($firstToken, self::TYPE_WORDS, true)) {
                continue; // tolerant skip
            }

            if ($i + $blockSize - 1 >= $count) {
                break; // incomplete trailing record — skip
            }

            $block = array_slice($lines, $i, $blockSize);

            $event = $scope === OccultationScope::GLOBAL
                ? $this->parseGlobal($block)
                : $this->parseLocal($block);

            if ($event !== null) {
                $events[] = $event;
            }

            $i += $blockSize - 1; // advance past the consumed block
        }

        return new OccultationCollection($events);
    }

    // -------------------------------------------------------------------------
    // Global record (3 lines)
    // -------------------------------------------------------------------------

    /**
     * Global record:
     *   L1 tokens: [0]=type [1]=centrality? [offset]=date [offset+1]=time
     *              [offset+2]=coreShadow [offset+3]='km' [offset+4]=magnitude [offset+5]=JD
     *   L2 tokens: [0]=c1 [1]=c2 [2]=c3 [3]=c4 [4]=dt=<x>
     *   L3 tokens: angle group 0 = lon, angle group 1 = lat (DMS, may be split)
     *
     * @param  array<int, string>  $block
     */
    private function parseGlobal(array $block): ?OccultationEventData
    {
        $l1 = $this->tokens($block[0]);
        $l2 = $this->tokens($block[1]);
        $l3 = $this->tokens($block[2]);

        // [0] type word
        $type = OccultationType::tryFrom($l1[0] ?? '');
        if ($type === null) {
            return null;
        }

        // [1] optional centrality qualifier shifts remaining columns by 1
        $centrality = Centrality::tryFrom($l1[1] ?? '');
        $offset = $centrality !== null ? 2 : 1; // index of the date token

        $date = $l1[$offset] ?? null;        // e.g. 18.08.2033
        $time = $l1[$offset + 1] ?? null;    // e.g. 12:51:36.0
        $coreShadowRaw = $l1[$offset + 2] ?? null; // e.g. -3476.300002  (next token is 'km')
        // $l1[$offset + 3] = 'km'  — skipped
        $magnitudeRaw = $l1[$offset + 4] ?? null;  // e.g. 1.000000
        $julianDayRaw = $l1[$offset + 5] ?? null;  // e.g. 2463828.035833

        $maxAt = $this->parseDateTime($date, $time);
        if ($maxAt === null) {
            return null;
        }

        $magnitude = $this->toFloat($magnitudeRaw);
        $julianDay = $this->toFloat($julianDayRaw);
        if ($magnitude === null || $julianDay === null) {
            return null;
        }

        $coreShadowKm = $this->toFloat($coreShadowRaw); // null when token is '-' or missing

        // L2: contacts then dt=
        $contacts = $this->parseContacts($l2, $date);
        $deltaT = $this->parseDeltaT($l2);

        // L3: lon / lat in D°M' format (may be split across tokens)
        $location = $this->parseLocation($l3);

        return new OccultationEventData(
            type: $type,
            centrality: $centrality,
            scope: OccultationScope::GLOBAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT ?? 0.0,
            magnitude: $magnitude,
            coreShadowKm: $coreShadowKm,
            contacts: $contacts,
            location: $location,
            duration: null,
        );
    }

    // -------------------------------------------------------------------------
    // Local record (2 lines)
    // -------------------------------------------------------------------------

    /**
     * Local record:
     *   L1 tokens: [0]=type [1]=date [2]=time [3]=magnitude [4]=JD
     *   L2 tokens: [0]=N [1]='min' [2]=S.s [3]='sec' [4..7]=contacts [8]=dt=<x>
     *
     * @param  array<int, string>  $block
     */
    private function parseLocal(array $block): ?OccultationEventData
    {
        $l1 = $this->tokens($block[0]);
        $l2 = $this->tokens($block[1]);

        // [0] type word
        $type = OccultationType::tryFrom($l1[0] ?? '');
        if ($type === null) {
            return null;
        }

        $date = $l1[1] ?? null;           // e.g. 29.01.2034
        $time = $l1[2] ?? null;           // e.g. 16:04:52.8
        $magnitudeRaw = $l1[3] ?? null;   // e.g. 1.000000
        $julianDayRaw = $l1[4] ?? null;   // e.g. 2463992.170056

        $maxAt = $this->parseDateTime($date, $time);
        if ($maxAt === null) {
            return null;
        }

        $magnitude = $this->toFloat($magnitudeRaw);
        $julianDay = $this->toFloat($julianDayRaw);
        if ($magnitude === null || $julianDay === null) {
            return null;
        }

        // L2: "N min S.s sec" then contacts then dt=
        $duration = $this->parseDuration($l2);

        // Strip the leading duration tokens ("N", "min", "S.s", "sec") so
        // contact columns start at index 0.
        $contactTokens = $this->stripDurationTokens($l2);
        $contacts = $this->parseContacts($contactTokens, $date);
        $deltaT = $this->parseDeltaT($l2);

        return new OccultationEventData(
            type: $type,
            centrality: null,
            scope: OccultationScope::LOCAL,
            maxAt: $maxAt,
            julianDay: $julianDay,
            deltaT: $deltaT ?? 0.0,
            magnitude: $magnitude,
            coreShadowKm: null,
            contacts: $contacts,
            location: null,
            duration: $duration,
        );
    }

    // -------------------------------------------------------------------------
    // Contact / location / angle helpers
    // -------------------------------------------------------------------------

    /**
     * Build OccultationContacts from the first four contact columns.
     * `-` placeholders become null. Non-time tokens (e.g. 'dt=...') stop iteration.
     *
     * @param  array<int, string>  $tokens  starting at the first contact column
     */
    private function parseContacts(array $tokens, ?string $date): OccultationContacts
    {
        $cols = [];

        foreach ($tokens as $tok) {
            if (count($cols) === 4) {
                break;
            }
            if (str_starts_with($tok, 'dt=')) {
                break;
            }
            if ($tok === '-') {
                $cols[] = null;

                continue;
            }
            if (! $this->isTimeToken($tok)) {
                continue; // e.g. stray whitespace-only artefacts — skip
            }
            $cols[] = $this->parseDateTime($date, $tok);
        }

        // Pad to four slots with null.
        $cols = array_pad($cols, 4, null);

        return new OccultationContacts(
            exteriorStart: $cols[0], // [0] first exterior contact
            interiorStart: $cols[1], // [1] interior ingress
            interiorEnd: $cols[2],   // [2] interior egress
            exteriorEnd: $cols[3],   // [3] last exterior contact
        );
    }

    /**
     * Parse two DMS angles from L3 token list → OccultationLocation.
     * swetest may split degree and minute onto separate tokens (e.g. `107°` `7'`)
     * or keep them joined (`72°35'`). Re-join then regex.
     *
     * @param  array<int, string>  $tokens
     */
    private function parseLocation(array $tokens): ?OccultationLocation
    {
        $lon = $this->parseAngle($tokens, 0);
        $lat = $this->parseAngle($tokens, 1);

        if ($lon === null || $lat === null) {
            return null;
        }

        return new OccultationLocation(longitude: $lon, latitude: $lat);
    }

    /**
     * Extract one angle (by 0-based match index) from a joined token string.
     * Pattern matches: optional-sign degrees° [space] [minutes' [space] [seconds"]]
     * The minutes and seconds groups are non-capturing optionals anchored to
     * their respective suffix characters (', ") so they do NOT greedy-consume
     * across the degree symbol of the next coordinate.
     *
     * @param  array<int, string>  $tokens
     */
    private function parseAngle(array $tokens, int $which): ?float
    {
        $joined = implode(' ', $tokens);

        // [1]=deg(with sign) [2]=min (optional, ends with ') [3]=sec (optional, ends with ")
        $pattern = '/(-?\d+)°(?:\s*(\d+(?:\.\d+)?)\s*\')?(?:\s*(\d+(?:\.\d+)?)\s*\")?/u';

        $matches = [];
        if (preg_match_all($pattern, $joined, $matches, PREG_SET_ORDER) === false
            || ! isset($matches[$which])) {
            return null;
        }

        $m = $matches[$which];
        $deg = (float) $m[1];
        $min = isset($m[2]) ? (float) $m[2] : 0.0;
        $sec = isset($m[3]) ? (float) $m[3] : 0.0;
        $sign = $deg < 0 ? -1.0 : 1.0;

        return $sign * (abs($deg) + $min / 60.0 + $sec / 3600.0);
    }

    // -------------------------------------------------------------------------
    // Duration helpers
    // -------------------------------------------------------------------------

    /**
     * Parse "N min S.s sec" from token list → total seconds.
     * Result rounded to 2 dp to avoid float drift.
     *
     * @param  array<int, string>  $tokens
     */
    private function parseDuration(array $tokens): ?float
    {
        $joined = implode(' ', $tokens);
        if (preg_match('/(\d+(?:\.\d+)?)\s*min\s*(\d+(?:\.\d+)?)\s*sec/u', $joined, $m) === 1) {
            return round(((float) $m[1]) * 60.0 + (float) $m[2], 2);
        }

        return null;
    }

    /**
     * Remove the leading "N min S sec" tokens, returning the remainder starting
     * at the first contact column.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function stripDurationTokens(array $tokens): array
    {
        foreach ($tokens as $idx => $tok) {
            if ($tok === 'sec') {
                return array_values(array_slice($tokens, $idx + 1));
            }
        }

        return $tokens;
    }

    // -------------------------------------------------------------------------
    // deltaT helper
    // -------------------------------------------------------------------------

    /**
     * Find the `dt=<x>` token in a token list and return its numeric value.
     *
     * @param  array<int, string>  $tokens
     */
    private function parseDeltaT(array $tokens): ?float
    {
        foreach ($tokens as $tok) {
            if (str_starts_with($tok, 'dt=')) {
                return $this->toFloat(substr($tok, 3));
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // DateTime helpers
    // -------------------------------------------------------------------------

    /**
     * Build a UTC Carbon from a `d.m.Y` date and `H:i:s[.f]` time token.
     * Returns null for invalid or placeholder `-` tokens.
     */
    private function parseDateTime(?string $date, ?string $time): ?Carbon
    {
        if ($date === null || $time === null) {
            return null;
        }
        if (! preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $date)) {
            return null;
        }
        if (! $this->isTimeToken($time)) {
            return null;
        }

        $parts = explode('.', $time, 2);
        $hms = $parts[0];
        $fracRaw = $parts[1] ?? '0';
        // Pad / truncate to microseconds (6 digits).
        $micros = (int) str_pad(substr($fracRaw, 0, 6), 6, '0', STR_PAD_RIGHT);

        $dt = Carbon::createFromFormat('d.m.Y H:i:s', "{$date} {$hms}", 'UTC');
        if (! ($dt instanceof Carbon)) {
            return null;
        }

        return $dt->setMicroseconds($micros);
    }

    /** Returns true iff the token looks like H:i:s or H:i:s.f */
    private function isTimeToken(string $token): bool
    {
        return (bool) preg_match('/^\d{1,2}:\d{2}:\d{2}(\.\d+)?$/', $token);
    }

    // -------------------------------------------------------------------------
    // Token helpers
    // -------------------------------------------------------------------------

    private function toFloat(?string $token): ?float
    {
        if ($token === null || $token === '' || $token === '-') {
            return null;
        }
        if (! is_numeric($token)) {
            return null;
        }

        return (float) $token;
    }

    private function firstToken(string $line): string
    {
        return $this->tokens($line)[0] ?? '';
    }

    /**
     * Split a line on whitespace, filtering empty fragments.
     *
     * @return array<int, string>
     */
    private function tokens(string $line): array
    {
        $parts = preg_split('/\s+/', trim($line)) ?: [];

        return array_values(array_filter($parts, static fn ($t) => $t !== ''));
    }
}
