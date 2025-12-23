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
            self::CO_ASC_KOCH => 'CO-Ascendant" (W. Koch)',
            self::CO_ASC_MUNKASEY => 'CO-Ascendant" (M. Munkasey)',
            self::POLAR_ASC_MUNKASEY => 'Polar Ascendant" (M. Munkasey)'
        };
    }
}
