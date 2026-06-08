<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class SarosSeries extends Data
{
    public function __construct(
        public readonly int $series,
        public readonly int $member,
    ) {}
}
