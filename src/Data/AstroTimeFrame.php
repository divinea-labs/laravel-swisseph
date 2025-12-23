<?php

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class AstroTimeFrame extends Data
{
    public function __construct(
        public ?int $id,
        public string $place,
        public Carbon $date,
        public float $longitude,
        public float $latitude,
        public ?string $house_system,
        public array $planet_bodies,
        public array $houses,
    ) {}
}
