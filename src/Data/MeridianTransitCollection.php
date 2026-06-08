<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class MeridianTransitCollection extends Data
{
    /**
     * @param  array<int, MeridianTransitData>  $transits
     */
    public function __construct(
        public readonly array $transits,
    ) {}

    /**
     * @return array<int, MeridianTransitData>
     */
    public function all(): array
    {
        return array_values($this->transits);
    }

    public function first(): ?MeridianTransitData
    {
        return $this->all()[0] ?? null;
    }

    public function count(): int
    {
        return count($this->transits);
    }
}
