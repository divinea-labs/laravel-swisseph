<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use DivineaLabs\Swisseph\Enums\PlanetBody;
use Spatie\LaravelData\Data;

/**
 * Osculating orbital elements relative to the mean ecliptic J2000, as produced
 * by swetest's -orbel mode. All values vary with the calculation date.
 */
class OrbitalElementsData extends Data
{
    public function __construct(
        public readonly PlanetBody $body,
        public readonly float $semiAxis,
        public readonly float $eccentricity,
        public readonly float $inclination,
        public readonly float $ascendingNode,
        public readonly float $argPericenter,
        public readonly float $pericenter,
        public readonly float $meanLongitude,
        public readonly float $meanAnomaly,
        public readonly float $eccentricAnomaly,
        public readonly float $trueAnomaly,
        /** Julian day (TT) of pericenter passage. */
        public readonly float $timePericenterJd,
        /** Civil date string of pericenter passage as printed by swetest. */
        public readonly string $timePericenterCivil,
        public readonly float $distPericenter,
        public readonly float $distApocenter,
        public readonly float $meanDailyMotion,
        public readonly float $siderealPeriodYears,
        public readonly float $tropicalPeriodYears,
        public readonly float $synodicCycleDays,
    ) {}
}
