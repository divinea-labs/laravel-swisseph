<?php

namespace DivineaLabs\Swisseph\Enums;

enum House: string
{
    case HOUSE_1 = '1';
    case HOUSE_2 = '2';
    case HOUSE_3 = '3';
    case HOUSE_4 = '4';
    case HOUSE_5 = '5';
    case HOUSE_6 = '6';
    case HOUSE_7 = '7';
    case HOUSE_8 = '8';
    case HOUSE_9 = '9';
    case HOUSE_10 = '10';
    case HOUSE_11 = '11';
    case HOUSE_12 = '12';
    case ASCENDANT = '13';
    case MC = '14';
    case ARMC = '15';
    case VERTEX = '16';
    case EQUAT_ASC = '17';
    case CO_ASC_KOCH = '18';
    case CO_ASC_MUNKASEY = '19';
    case POLAR_ASC_MUNKASEY = '20';

    public function getName(): string
    {
        return match ($this) {
            self::HOUSE_1 => 'House 1',
            self::HOUSE_2 => 'House 2',
            self::HOUSE_3 => 'House 3',
            self::HOUSE_4 => 'House 4',
            self::HOUSE_5 => 'House 5',
            self::HOUSE_6 => 'House 6',
            self::HOUSE_7 => 'House 7',
            self::HOUSE_8 => 'House 8',
            self::HOUSE_9 => 'House 9',
            self::HOUSE_10 => 'House 10',
            self::HOUSE_11 => 'House 11',
            self::HOUSE_12 => 'House 12',
            self::ASCENDANT => 'Ascendant',
            self::MC => 'Midheaven',
            self::ARMC => 'ARMC',
            self::VERTEX => 'Vertex',
            self::EQUAT_ASC => 'Equatorial Ascendant',
            self::CO_ASC_KOCH => 'CO-Ascendant (W. Koch)',
            self::CO_ASC_MUNKASEY => 'CO-Ascendant (M. Munkasey)',
            self::POLAR_ASC_MUNKASEY => 'Polar Ascendant (M. Munkasey)'
        };
    }

    /**
     * The house number of the twelve cusps; null for the eight points that share this
     * enum (Ascendant, MC, ARMC, Vertex, ...). Swiss Ephemeris numbers those points
     * 13..20 in the same column as the cusps, so the raw backing value is not a house
     * number - ask this method instead.
     */
    public function cuspNumber(): ?int
    {
        $number = (int) $this->value;

        return $number <= 12 ? $number : null;
    }

    /**
     * A wire-safe identifier, stable across releases. Cusps are `house_1`..`house_12`;
     * the points use the names consumers already publish.
     */
    public function slug(): string
    {
        return match ($this) {
            self::HOUSE_1 => 'house_1',
            self::HOUSE_2 => 'house_2',
            self::HOUSE_3 => 'house_3',
            self::HOUSE_4 => 'house_4',
            self::HOUSE_5 => 'house_5',
            self::HOUSE_6 => 'house_6',
            self::HOUSE_7 => 'house_7',
            self::HOUSE_8 => 'house_8',
            self::HOUSE_9 => 'house_9',
            self::HOUSE_10 => 'house_10',
            self::HOUSE_11 => 'house_11',
            self::HOUSE_12 => 'house_12',
            self::ASCENDANT => 'ascendant',
            self::MC => 'midheaven',
            self::ARMC => 'armc',
            self::VERTEX => 'vertex',
            self::EQUAT_ASC => 'equatorial_ascendant',
            self::CO_ASC_KOCH => 'co_ascendant_koch',
            self::CO_ASC_MUNKASEY => 'co_ascendant_munkasey',
            self::POLAR_ASC_MUNKASEY => 'polar_ascendant',
        };
    }

    /**
     * The inverse of getName(). Null for a name belonging to no house or point, so an
     * unknown label is detectable rather than silently becoming a wrong row.
     */
    public static function fromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->getName() === $name) {
                return $case;
            }
        }

        return null;
    }
}
