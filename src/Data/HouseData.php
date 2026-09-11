<?php

namespace DivineaLabs\Swisseph\Data;

use DivineaLabs\Swisseph\Enums\House;
use Spatie\LaravelData\Data;

class HouseData extends Data
{
    public function __construct(
        public int $index,
        public string $name,
    ) {}

    /** The enum case this row describes - the typed way to ask which house or point it is. */
    public function house(): House
    {
        return House::from((string) $this->index);
    }
}
