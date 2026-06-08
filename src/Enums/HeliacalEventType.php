<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Enums;

enum HeliacalEventType: string
{
    case HELIACAL_RISING = 'heliacal_rising';
    case HELIACAL_SETTING = 'heliacal_setting';
    case MORNING_LAST = 'morning_last';
    case EVENING_FIRST = 'evening_first';

    /**
     * Resolve the event type from a printed swetest label such as
     * "Venus heliacal rising" or "Venus morning last" (the body words come
     * first, the event phrase last). Returns null if no phrase matches.
     */
    public static function fromLabel(string $label): ?self
    {
        $l = strtolower(trim($label));

        return match (true) {
            str_ends_with($l, 'heliacal rising') => self::HELIACAL_RISING,
            str_ends_with($l, 'heliacal setting') => self::HELIACAL_SETTING,
            str_ends_with($l, 'morning last') => self::MORNING_LAST,
            str_ends_with($l, 'evening first') => self::EVENING_FIRST,
            default => null,
        };
    }

    /** The printed swetest phrase for this event type. */
    public function phrase(): string
    {
        return match ($this) {
            self::HELIACAL_RISING => 'heliacal rising',
            self::HELIACAL_SETTING => 'heliacal setting',
            self::MORNING_LAST => 'morning last',
            self::EVENING_FIRST => 'evening first',
        };
    }
}
