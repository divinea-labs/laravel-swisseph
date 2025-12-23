<?php

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class HouseData extends Data
{
    public function __construct(
        public int $index,
        public string $name,
    ) {}
}
