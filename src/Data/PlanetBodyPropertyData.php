<?php

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class PlanetBodyPropertyData extends Data
{
    public function __construct(
        public string $label,
        public string $property,
        public string|float|array $value
    ) {}
}
