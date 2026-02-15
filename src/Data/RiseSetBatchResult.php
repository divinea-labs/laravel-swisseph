<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use DivineaLabs\Swisseph\Enums\PlanetBody;
use Spatie\LaravelData\Data;

class RiseSetBatchResult extends Data
{
    public function __construct(
        /** @var array<int, RiseSetResult>  keyed by PlanetBody->value */
        public readonly array $results,
    ) {}

    /**
     * Look up the result for a specific body, or null if not in this batch.
     */
    public function forBody(PlanetBody $body): ?RiseSetResult
    {
        return $this->results[$body->value] ?? null;
    }

    /**
     * Return all per-body results as a plain array.
     *
     * @return RiseSetResult[]
     */
    public function all(): array
    {
        return array_values($this->results);
    }
}
