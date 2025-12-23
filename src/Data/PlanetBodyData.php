<?php

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class PlanetBodyData extends Data
{
    public function __construct(
        public int $index,
        public string $name,
    ) {}
}
