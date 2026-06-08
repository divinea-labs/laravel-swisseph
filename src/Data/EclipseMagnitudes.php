<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class EclipseMagnitudes extends Data
{
    public function __construct(
        public readonly float $primary,
        public readonly float $secondary,
        public readonly ?float $tertiary = null,
    ) {}
}
