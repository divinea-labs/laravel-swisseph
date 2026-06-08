<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Heliacal;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\HeliacalCollection;
use DivineaLabs\Swisseph\Data\HeliacalEventData;
use DivineaLabs\Swisseph\Enums\HeliacalEventType;

class HeliacalParser
{
    /**
     * One line per heliacal event. The label is left-justified and padded, so the
     * separating colon may or may not have a leading space:
     *
     *   Venus heliacal rising : 2026/11/02 04:41:55.9 UT (2461346.69579), opt 04:56:15.9, end 05:17:59.9, dur 36.1 min
     *   Venus heliacal setting: 2028/05/23 18:56:52.6 UT (2461915.28950), opt 19:21:06.6, end 19:38:51.6, dur 42.0 min
     *
     * The colon that splits the label from the data is the one followed by the
     * `YYYY/MM/DD` date. The label = "<body words> <event phrase>".
     *
     * @param  array<int, string>  $lines
     */
    public function parse(array $lines): HeliacalCollection
    {
        $pattern = '#^(.+?):\s+(\d{4})/(\d{2})/(\d{2})\s+(\d{1,2}:\d{2}:\d{2}(?:\.\d+)?)\s+UT\s+\(([\d.]+)\),'
            .'\s+opt\s+(\d{1,2}:\d{2}:\d{2}(?:\.\d+)?),\s+end\s+(\d{1,2}:\d{2}:\d{2}(?:\.\d+)?),\s+dur\s+([\d.]+)\s+min#';

        $events = [];

        foreach ($lines as $line) {
            if (! preg_match($pattern, trim($line), $m)) {
                continue; // tolerant: skip the geo header and any non-event line
            }

            $label = trim($m[1]);
            $type = HeliacalEventType::fromLabel($label);
            if ($type === null) {
                continue;
            }

            // Body = label with the trailing event phrase removed.
            $body = trim(substr($label, 0, strlen($label) - strlen($type->phrase())));

            $ymd = "{$m[2]}-{$m[3]}-{$m[4]}";
            $at = $this->timeAt($ymd, $m[5]);
            $optimumAt = $this->timeAt($ymd, $m[7]);
            $endAt = $this->timeAt($ymd, $m[8]);

            if ($at === null || $optimumAt === null || $endAt === null) {
                continue;
            }

            $events[] = new HeliacalEventData(
                body: $body,
                type: $type,
                at: $at,
                julianDay: (float) $m[6],
                optimumAt: $optimumAt,
                endAt: $endAt,
                durationMinutes: (float) $m[9],
            );
        }

        return new HeliacalCollection(events: $events);
    }

    private function timeAt(string $ymd, string $time): ?Carbon
    {
        $parts = explode('.', $time, 2);
        $hms = $parts[0];
        $fracRaw = $parts[1] ?? '0';
        $micros = (int) str_pad(substr($fracRaw, 0, 6), 6, '0', STR_PAD_RIGHT);

        $dt = Carbon::createFromFormat('Y-m-d H:i:s', "{$ymd} {$hms}", 'UTC');
        if (! ($dt instanceof Carbon)) {
            return null;
        }

        return $dt->setMicroseconds($micros);
    }
}
