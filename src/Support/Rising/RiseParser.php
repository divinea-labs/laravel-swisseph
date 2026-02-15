<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Rising;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\RiseSetEvent;
use DivineaLabs\Swisseph\Data\RiseSetResult;
use DivineaLabs\Swisseph\Enums\RiseSetEventType;

final class RiseParser
{
    /**
     * Parse swetest -rise output for one body and return a filtered RiseSetResult.
     *
     * Contract: this method never throws. All validation is guard-and-continue.
     * Strict-mode exceptions, if any, are raised by the service layer after parsing.
     */
    public function parse(array $lines, RiseQuery $query): RiseSetResult
    {
        $events = [];

        $datePattern = '/^\d{2}\.\d{2}\.\d{4}$/';
        $timePattern = '/^\d{2}:\d{2}:\d{2}(\.\d+)?$/';

        foreach ($lines as $line) {
            if (! str_starts_with((string) $line, 'rise')) {
                continue;
            }

            $tokens = preg_split('/\s+/', trim((string) $line));

            // Structural and format validation — skip malformed/placeholder lines
            if (count($tokens) < 6) {
                continue;
            }
            if ($tokens[3] !== 'set') {
                continue;
            }
            if (! preg_match($datePattern, $tokens[1])) {
                continue;
            }
            if (! preg_match($datePattern, $tokens[4])) {
                continue;
            }
            if (! preg_match($timePattern, $tokens[2])) {
                continue;
            }
            if (! preg_match($timePattern, $tokens[5])) {
                continue;
            }

            $riseUtc = $this->parseUtcDateTime($tokens[1], $tokens[2]);
            $setUtc  = $this->parseUtcDateTime($tokens[4], $tokens[5]);

            if ($riseUtc === null || $setUtc === null) {
                continue;   // parseUtcDateTime never throws
            }

            if ($query->isModeB()) {
                $events[] = RiseSetEvent::withTimezone(
                    $query->body, RiseSetEventType::RISE, $riseUtc, $query->timezone
                );
                $events[] = RiseSetEvent::withTimezone(
                    $query->body, RiseSetEventType::SET, $setUtc, $query->timezone
                );
            } else {
                $events[] = RiseSetEvent::utcOnly($query->body, RiseSetEventType::RISE, $riseUtc);
                $events[] = RiseSetEvent::utcOnly($query->body, RiseSetEventType::SET, $setUtc);
            }
        }

        // Filter events to the requested day
        $filtered = array_values(array_filter(
            $events,
            fn (RiseSetEvent $e) => $query->isModeB()
                ? $e->localDate === $query->localDate
                : $e->utcDate   === $query->utcDate
        ));

        $riseFound = false;
        $setFound  = false;
        foreach ($filtered as $e) {
            if ($e->type === RiseSetEventType::RISE) {
                $riseFound = true;
            }
            if ($e->type === RiseSetEventType::SET) {
                $setFound = true;
            }
        }

        return new RiseSetResult(
            body:      $query->body,
            utcDate:   $query->utcDate,
            localDate: $query->localDate,
            timezone:  $query->timezone,
            longitude: $query->longitude,
            latitude:  $query->latitude,
            elevation: $query->elevation,
            discMode:  $query->discMode,
            events:    $filtered,
            riseFound: $riseFound,
            setFound:  $setFound,
        );
    }

    /**
     * Parse a dd.mm.yyyy date and HH:MM:SS[.f] time into a UTC Carbon.
     *
     * Returns null if parsing fails — caller skips the line.
     * Must never throw; strict-mode exceptions are raised only in the service layer.
     */
    private function parseUtcDateTime(string $date, string $time): ?Carbon
    {
        $parts   = explode('.', $time, 2);
        $hms     = $parts[0];           // 'HH:MM:SS'
        $fracRaw = $parts[1] ?? '0';

        // Normalise to 6 digits: '9' → '900000', '94' → '940000', '' → '000000'
        $micros = (int) str_pad(substr($fracRaw, 0, 6), 6, '0', STR_PAD_RIGHT);

        $dt = Carbon::createFromFormat('d.m.Y H:i:s', "{$date} {$hms}", 'UTC');

        if ($dt === false) {
            return null;
        }

        return $dt->setMicroseconds($micros);
    }
}
