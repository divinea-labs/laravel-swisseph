<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use Spatie\LaravelData\Data;

class MeridianTransitData extends Data
{
    public function __construct(
        public readonly PlanetBody $body,
        /** Upper (southern) meridian transit — swetest `mtransit`. */
        public readonly Carbon $upperTransitAt,
        /** Lower (northern) meridian transit — swetest `itransit`. */
        public readonly Carbon $lowerTransitAt,
        /** Calendar day (Y-m-d, UTC) of the upper transit. */
        public readonly string $date,
    ) {}
}
