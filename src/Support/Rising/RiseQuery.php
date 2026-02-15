<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Rising;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\DiscMode;
use DivineaLabs\Swisseph\Enums\PlanetBody;

/**
 * Value object carrying all caller intent from RiseCommandBuilder through to RiseParser.
 * Never exposed in the public API.
 */
final class RiseQuery
{
    public function __construct(
        // Body being queried (pX token). Typed as PlanetBody for now.
        // Future extension point: replace with a RiseTarget union (PlanetBody|FixedStar)
        // when fixed-star rise/set support is added. RiseQuery and RiseSetEvent are
        // intentionally generic in naming to accommodate this without renaming.
        public readonly PlanetBody $body,
        public readonly Carbon     $windowStartUtc,     // UTC datetime for b (+ optional ut)
        public readonly ?string    $timezone,           // null = Mode A; non-null = Mode B
        public readonly ?string    $localDate,          // null in Mode A; 'Y-m-d' in Mode B
        public readonly string     $utcDate,            // 'Y-m-d' of windowStartUtc (Mode A filter)
        public readonly float      $longitude,
        public readonly float      $latitude,
        public readonly float      $elevation,
        public readonly DiscMode   $discMode,
        public readonly bool       $noRefraction,
        public readonly bool       $anchorToLocalMidnight,
        public readonly int        $windowDays,         // n value, default 3
        public readonly float      $stepDays,           // s value, default 1.0
        public readonly ?string    $atmosphericModel,   // formatted "at{...}" token or null
        public readonly ?string    $observerModel,      // formatted "obs{...}" token or null
        public readonly ?string    $opticalModel,       // formatted "opt{...}" token or null
    ) {}

    public function isModeB(): bool
    {
        return $this->timezone !== null;
    }
}
