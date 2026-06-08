<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use DivineaLabs\Swisseph\Enums\HeliacalEventType;
use Spatie\LaravelData\Data;

class HeliacalCollection extends Data
{
    /**
     * @param  array<int, HeliacalEventData>  $events
     */
    public function __construct(
        public readonly array $events,
    ) {}

    /**
     * @return array<int, HeliacalEventData>
     */
    public function all(): array
    {
        return array_values($this->events);
    }

    public function first(): ?HeliacalEventData
    {
        return $this->all()[0] ?? null;
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return array<int, HeliacalEventData>
     */
    public function ofType(HeliacalEventType $type): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (HeliacalEventData $e) => $e->type === $type,
        ));
    }
}
