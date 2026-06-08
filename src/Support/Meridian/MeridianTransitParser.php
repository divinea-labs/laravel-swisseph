<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Meridian;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\MeridianTransitCollection;
use DivineaLabs\Swisseph\Data\MeridianTransitData;
use DivineaLabs\Swisseph\Enums\PlanetBody;

class MeridianTransitParser
{
    /**
     * Parse swetest `-metr` output: one line per day, each carrying both the
     * upper (southern, `mtransit`) and lower (northern, `itransit`) meridian
     * transit times. The command-echo and `geo. long …` header lines are
     * already stripped by the executor.
     *
     * Line shape (whitespace = mixed tabs/spaces):
     *   mtransit  1.01.2026   10:39:32.3    itransit  1.01.2026   22:39:46.3
     *   tokens: [0]=mtransit [1]=date [2]=time [3]=itransit [4]=date [5]=time
     *
     * @param  array<int, string>  $lines
     */
    public function parse(array $lines, PlanetBody $body): MeridianTransitCollection
    {
        $transits = [];

        foreach ($lines as $line) {
            $tokens = $this->tokens($line);

            // A valid record starts with `mtransit` and has the `itransit` keyword at [3].
            if (count($tokens) < 6 || $tokens[0] !== 'mtransit' || $tokens[3] !== 'itransit') {
                continue;
            }

            $upper = $this->parseDateTime($tokens[1], $tokens[2]);
            $lower = $this->parseDateTime($tokens[4], $tokens[5]);

            if ($upper === null || $lower === null) {
                continue; // tolerant: skip malformed records
            }

            $transits[] = new MeridianTransitData(
                body: $body,
                upperTransitAt: $upper,
                lowerTransitAt: $lower,
                date: $upper->format('Y-m-d'),
            );
        }

        return new MeridianTransitCollection(transits: $transits);
    }

    private function parseDateTime(string $date, string $time): ?Carbon
    {
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
     * Split a line on whitespace, dropping empty fragments.
     *
     * @return array<int, string>
     */
    private function tokens(string $line): array
    {
        $parts = preg_split('/\s+/', trim($line)) ?: [];

        return array_values(array_filter($parts, static fn ($t) => $t !== ''));
    }
}
