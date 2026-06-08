<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class OccultationCollection extends Data
{
    /**
     * @param  array<int, OccultationEventData>  $events
     */
    public function __construct(
        public readonly array $events,
    ) {}

    /**
     * @return array<int, OccultationEventData>
     */
    public function all(): array
    {
        return array_values($this->events);
    }

    public function first(): ?OccultationEventData
    {
        return $this->all()[0] ?? null;
    }

    public function count(): int
    {
        return count($this->events);
    }
}
