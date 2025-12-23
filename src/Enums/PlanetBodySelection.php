<?php

namespace DivineaLabs\Swisseph\Enums;

enum PlanetBodySelection: string
{
    case DEFAULT_FACTORS = 'd';
    case DEFAULT_FACTORS_ASTEROIDS = 'p';
    case FICTITIOUS_FACTORS = 'h';
    case ALL_FACTORS = 'a';
    case SUN = '0';
    case MOON = '1';
    case MERCURY = '2';
    case VENUS = '3';
    case MARS = '4';
    case JUPITER = '5';
    case SATURN = '6';
    case URANUS = '7';
    case NEPTUNE = '8';
    case PLUTO = '9';
    case MEAN_NODE = 'm';
    case TRUE_NODE = 't';
    case MEAN_APOG = 'A'; // Lilith
    case OSCU_APOG = 'B';
    case EARTH = 'C';
    case CHIRON = 'D';
    case PHOLUS = 'E';
    case CERES = 'F';
    case PALLAS = 'G';
    case JUNO = 'H';
    case VESTA = 'I';
    case INTP_APOG = 'c'; // Lunar apogee
    case INTP_PERG = 'g';
    case NPLANETS = '?';
    case CUPIDO = 'J';
    case HADES = 'K';
    case ZEUS = 'L';
    case KRONOS = 'M';
    case APOLLON = 'N';
    case ADMETOS = 'O';
    case VULKANUS = 'P';
    case POSEIDON = 'Q';
    case ISIS = 'R';
    case NIBIRU = 'S';
    case HARRINGTON = 'T';
    case NEPTUNE_LEVERRIER = 'U';
    case NEPTUNE_ADAMS = 'V';
    case PLUTO_LOWELL = 'W';
    case PLUTO_PICKERING = 'X';
    case VULCAN = 'Y';
    case SELENA = 'Z'; // White Moon
    case WALDEMATH = 'w'; // Waldemath's dark Moon

    /*
     *  x sidereal time
        e print a line of labels
     */

    public function getName(): string
    {
        return match ($this) {

            // Selection groups
            self::DEFAULT_FACTORS => 'Default planetary factors',
            self::DEFAULT_FACTORS_ASTEROIDS => 'Default asteroid set',
            self::FICTITIOUS_FACTORS => 'Fictitious / hypothetical bodies',
            self::ALL_FACTORS => 'All available planetary factors',
            self::SUN => 'Sun',
            self::MOON => 'Moon',
            self::MERCURY => 'Mercury',
            self::VENUS => 'Venus',
            self::MARS => 'Mars',
            self::JUPITER => 'Jupiter',
            self::SATURN => 'Saturn',
            self::URANUS => 'Uranus',
            self::NEPTUNE => 'Neptune',
            self::PLUTO => 'Pluto',
            self::MEAN_NODE => 'Mean Node',
            self::TRUE_NODE => 'True Node',
            self::MEAN_APOG => 'Mean Apogee (Lilith)',
            self::OSCU_APOG => 'Osculating Apogee',
            self::EARTH => 'Earth',
            self::CHIRON => 'Chiron',
            self::PHOLUS => 'Pholus',
            self::CERES => 'Ceres',
            self::PALLAS => 'Pallas',
            self::JUNO => 'Juno',
            self::VESTA => 'Vesta',
            self::INTP_APOG => 'Interpolated Apogee',
            self::INTP_PERG => 'Interpolated Perigee',
            self::NPLANETS => 'Number of planets',
            self::CUPIDO => 'Cupido',
            self::HADES => 'Hades',
            self::ZEUS => 'Zeus',
            self::KRONOS => 'Kronos',
            self::APOLLON => 'Apollon',
            self::ADMETOS => 'Admetos',
            self::VULKANUS => 'Vulkanus',
            self::POSEIDON => 'Poseidon',
            self::ISIS => 'Isis',
            self::NIBIRU => 'Nibiru',
            self::HARRINGTON => 'Harrington',
            self::NEPTUNE_LEVERRIER => 'Neptune (Leverrier)',
            self::NEPTUNE_ADAMS => 'Neptune (Adams)',
            self::PLUTO_LOWELL => 'Pluto (Lowell)',
            self::PLUTO_PICKERING => 'Pluto (Pickering)',
            self::VULCAN => 'Vulcan',
            self::SELENA => 'Selena / White Moon',
            self::WALDEMATH => 'Waldemath\'s Dark Moon',
        };
    }
}
