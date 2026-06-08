<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

final class EclipseCollection
{
    /**
     * @param  list<EclipseEventData>  $events
     */
    public function __construct(
        private readonly array $events = [],
    ) {}

    /**
     * @return list<EclipseEventData>
     */
    public function all(): array
    {
        return $this->events;
    }

    public function first(): ?EclipseEventData
    {
        return $this->events[0] ?? null;
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return list<EclipseEventData>
     */
    public function solarOnly(): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (EclipseEventData $e) => $e->isSolar(),
        ));
    }

    /**
     * @return list<EclipseEventData>
     */
    public function lunarOnly(): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (EclipseEventData $e) => $e->isLunar(),
        ));
    }
}
