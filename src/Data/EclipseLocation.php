<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class EclipseLocation extends Data
{
    public function __construct(
        public readonly float $longitude,
        public readonly float $latitude,
    ) {}
}
