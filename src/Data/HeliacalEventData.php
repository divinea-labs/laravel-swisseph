<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\HeliacalEventType;
use Spatie\LaravelData\Data;

class HeliacalEventData extends Data
{
    public function __construct(
        /** Body or star name as printed by swetest (e.g. "Venus"). */
        public readonly string $body,
        public readonly HeliacalEventType $type,
        /** Main event time (UT). */
        public readonly Carbon $at,
        public readonly float $julianDay,
        /** Optimum visibility time (UT). */
        public readonly Carbon $optimumAt,
        /** End-of-visibility time (UT). */
        public readonly Carbon $endAt,
        public readonly float $durationMinutes,
    ) {}
}
