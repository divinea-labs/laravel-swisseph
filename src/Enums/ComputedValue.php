<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

/**
 * Output-only pseudo-bodies appended to the -p selector.
 *
 * Each produces a single labeled row whose value column is a datum rather than a
 * normal position: Sidereal Time is a UT time STRING; the others are floats.
 * (Ayanamsha additionally requires a sidereal mode, e.g. -sid1 via withSidereal().)
 */
enum ComputedValue: string
{
    case SIDEREAL_TIME = 'x';
    case DELTA_T = 'q';
    case ECLIPTIC_OBLIQUITY = 'o';
    case AYANAMSHA = 'b';
    case TIME_EQUATION = 'y';

    /**
     * The fixed label swetest prints in the name column for this code.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::SIDEREAL_TIME => 'Sidereal Time',
            self::DELTA_T => 'Delta T',
            self::ECLIPTIC_OBLIQUITY => 'Ecl. Obl.',
            self::AYANAMSHA => 'Ayanamsha',
            self::TIME_EQUATION => 'Time equation',
        };
    }

    /**
     * Whether the value column is a UT time string (true) or a numeric datum (false).
     */
    public function isTimeString(): bool
    {
        return $this === self::SIDEREAL_TIME;
    }

    /**
     * Resolve a swetest name-column label back to its enum (null if not a computed value).
     */
    public static function fromLabel(string $label): ?self
    {
        $label = trim($label);

        foreach (self::cases() as $case) {
            if ($case->getLabel() === $label) {
                return $case;
            }
        }

        return null;
    }
}
