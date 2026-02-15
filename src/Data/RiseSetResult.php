<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\CarbonInterval;
use DivineaLabs\Swisseph\Enums\DiscMode;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Enums\RiseSetEventType;
use Spatie\LaravelData\Data;

class RiseSetResult extends Data
{
    public function __construct(
        public readonly PlanetBody $body,
        public readonly string $utcDate,     // always set
        public readonly ?string $localDate,   // null in Mode A
        public readonly ?string $timezone,    // null in Mode A
        public readonly float $longitude,
        public readonly float $latitude,
        public readonly float $elevation,
        public readonly DiscMode $discMode,
        /** @var RiseSetEvent[] */
        public readonly array $events,
        public readonly bool $riseFound,
        public readonly bool $setFound,
    ) {}

    /**
     * Return the first RISE event for the requested day, or null if none found.
     */
    public function rise(): ?RiseSetEvent
    {
        foreach ($this->events as $event) {
            if ($event->type === RiseSetEventType::RISE) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Return the first SET event for the requested day, or null if none found.
     */
    public function set(): ?RiseSetEvent
    {
        foreach ($this->events as $event) {
            if ($event->type === RiseSetEventType::SET) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Return the interval between rise and set utcAt, or null if either is missing.
     */
    public function dayLength(): ?CarbonInterval
    {
        $rise = $this->rise();
        $set = $this->set();

        if ($rise === null || $set === null) {
            return null;
        }

        return $rise->utcAt->diffAsCarbonInterval($set->utcAt);
    }
}
