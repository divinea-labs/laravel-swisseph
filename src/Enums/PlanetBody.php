<?php

namespace DivineaLabs\Swisseph\Enums;

enum PlanetBody: int
{
    case ECL_NUT = -1;
    case SUN = 0;
    case MOON = 1;
    case MERCURY = 2;
    case VENUS = 3;
    case MARS = 4;
    case JUPITER = 5;
    case SATURN = 6;
    case URANUS = 7;
    case NEPTUNE = 8;
    case PLUTO = 9;
    case MEAN_NODE = 10;
    case TRUE_NODE = 11;
    case MEAN_APOG = 12;
    case OSCU_APOG = 13;
    case EARTH = 14;
    case CHIRON = 15;
    case PHOLUS = 16;
    case CERES = 17;
    case PALLAS = 18;
    case JUNO = 19;
    case VESTA = 20;
    case INTP_APOG = 21;
    case INTP_PERG = 22;
    case NPLANETS = 23;

    // Uranian / hypothetical planets
    case CUPIDO = 40;
    case HADES = 41;
    case ZEUS = 42;
    case KRONOS = 43;
    case APOLLON = 44;
    case ADMETOS = 45;
    case VULKANUS = 46;
    case POSEIDON = 47;

    // Additional bodies
    case ISIS = 48;
    case NIBIRU = 49;
    case HARRINGTON = 50;
    case NEPTUNE_LEVERRIER = 51;
    case NEPTUNE_ADAMS = 52;
    case PLUTO_LOWELL = 53;
    case PLUTO_PICKERING = 54;
    case VULCAN = 55;
    case SELENA = 56;
    case WALDEMATH = 58;

    public function getName(): string
    {
        return match ($this) {
            self::ECL_NUT => 'Ecliptic / Nutation',
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
            self::NPLANETS => 'Planet count',
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
            self::WALDEMATH => 'Waldemath',
        };
    }

    public function getAdditionalInformation(): ?string
    {
        return match ($this) {
            self::HARRINGTON => `This is another attempt to predict Planet X's orbit and position from perturbations in the orbits of Uranus and Neptune.
                                It was published in The Astronomical Journal 96(4), October 1988, p. 1476ff. Its precision is meant to be of the order of
                                +/- 30 degrees. According to Harrington there is also the possibility that it is actually located in the opposite
                                constellation, i.e. Taurus instead of Scorpio. The planet has a mean solar distance of about 100 AU and a period of about
                                1000 years.`,
            self::NIBIRU => `A highly speculative planet derived from the theory of Zecharia Sitchin, who is an expert in ancient Mesopotamian history
                                and a "paleoastronomer". The elements have been supplied by Christian Woeltge, Hannover. This planet is interesting
                                because of its bizarre orbit. It moves in clockwise direction and has a period of 3600 years. Its orbit is extremely eccentric.
                                It has its perihelion within the asteroid belt, whereas its aphelion lies at about 12 times the mean distance of Pluto. In
                                spite of its retrograde motion, it seems to move counterclockwise in recent centuries. The reason is that it is so slow that
                                it does not even compensate the precession of the equinoxes.`,
            self::VULCAN => `This is a ‘hypothetical’ planet inside the orbit of Mercury (not identical to the "Uranian" planet Vulkanus). Orbital elements
                                according to L.H. Weston. Note that the speed of this “planet” does not agree with the Kepler laws. It is too fast by 10
                                degrees per year.`,
            self::SELENA => `This is a "hypothetical" planet inside the orbit of Mercury (not identical to the "Uranian" planet Vulkanus). Orbital elements
                                according to L.H. Weston. Note that the speed of this “planet” does not agree with the Kepler laws. It is too fast by 10
                                degrees per year.`,
            self::WALDEMATH => `This is another hypothetical second Moon of the Earth, postulated by a Dr. Waldemath in the Monthly Wheather Review
                                1/1898. Its distance from the Earth is 2.67 times the distance of the Moon, its daily motion about 3 degrees. The orbital
                                elements have been derived from Waldemath’s original data. There are significant differences from elements used in
                                earlier versions of Solar Fire, due to different interpretations of the values given by Waldemath. After a discussion
                                between Graham Dawson and Dieter Koch it has been agreed that the new solution is more likely to be correct. The new
                                ephemeris does not agree with Delphine Jay’s ephemeris either, which is obviously inconsistent with Waldemath’s data.
                                This body has never been confirmed. With its 700-km diameter and an apparent diameter of 2.5 arc min, this should
                                have been possible very soon after Waldemath’s publication.`,
            default => null,
        };
    }
}
