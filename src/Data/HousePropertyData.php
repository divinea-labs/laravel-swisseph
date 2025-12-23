<?php

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class HousePropertyData extends Data
{
    public function __construct(
        public string $label,
        public string $property,
        public float $value
    ) {}
}
