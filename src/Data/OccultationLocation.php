<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

/**
 * The sub-point (longitude/latitude, decimal degrees) of a global occultation.
 */
class OccultationLocation extends Data
{
    public function __construct(
        public readonly float $longitude,
        public readonly float $latitude,
    ) {}
}
