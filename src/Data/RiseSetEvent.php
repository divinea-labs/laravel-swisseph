<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Enums\RiseSetEventType;
use Spatie\LaravelData\Data;

class RiseSetEvent extends Data
{
    public function __construct(
        public readonly PlanetBody $body,
        public readonly RiseSetEventType $type,
        public readonly Carbon $utcAt,
        public readonly string $utcDate,   // 'Y-m-d' in UTC, always set
        public readonly ?Carbon $localAt,   // null in Mode A
        public readonly ?string $localDate, // null in Mode A
    ) {}

    /**
     * Mode A factory — no local time projection.
     */
    public static function utcOnly(PlanetBody $body, RiseSetEventType $type, Carbon $utcAt): self
    {
        return new self(
            body: $body,
            type: $type,
            utcAt: $utcAt,
            utcDate: $utcAt->format('Y-m-d'),
            localAt: null,
            localDate: null,
        );
    }

    /**
     * Mode B factory — project utcAt into the requested timezone.
     */
    public static function withTimezone(
        PlanetBody $body, RiseSetEventType $type, Carbon $utcAt, string $timezone
    ): self {
        $localAt = $utcAt->copy()->setTimezone($timezone);

        return new self(
            body: $body,
            type: $type,
            utcAt: $utcAt,
            utcDate: $utcAt->format('Y-m-d'),
            localAt: $localAt,
            localDate: $localAt->format('Y-m-d'),
        );
    }
}
