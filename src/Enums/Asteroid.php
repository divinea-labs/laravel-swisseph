<?php

namespace DivineaLabs\Swisseph\Enums;

/**
 * Curated set of commonly-used named asteroids and trans-Neptunian objects.
 *
 * The backing value is the Minor Planet Center (MPC) number (the int swetest
 * expects after `-xs`). This is a convenience subset — any MPC number from
 * `ephe/seasnam.txt` may still be passed to selectAsteroid() as a raw int.
 *
 * Ceres, Pallas, Juno, Vesta, Chiron and Pholus are intentionally absent: they
 * have reserved Swiss Ephemeris constants and live in PlanetBody, selected via
 * selectBodies() rather than selectAsteroid().
 */
enum Asteroid: int
{
    case EROS = 433;
    case PSYCHE = 16;
    case SAPPHO = 80;
    case AMOR = 1221;
    case LILITH = 1181;
    case HYGIEA = 10;
    case APOLLO = 1862;
    case ICARUS = 1566;
    case ATEN = 2062;
    case CRUITHNE = 3753;
    case POSEIDON = 4341;
    case QUAOAR = 50000;
    case HIDALGO = 944;
    case NESSUS = 7066;
    case HEKATE = 100;
    case NEMESIS = 128;
    case FORTUNA = 19;
    case URANIA = 30;
    case HEKTOR = 624;
    case CHAOS = 19521;
    case SEDNA = 90377;
    case ERIS = 136199;
    case ORCUS = 90482;
    case VARUNA = 20000;

    public function getName(): string
    {
        return match ($this) {
            self::EROS => 'Eros',
            self::PSYCHE => 'Psyche',
            self::SAPPHO => 'Sappho',
            self::AMOR => 'Amor',
            self::LILITH => 'Lilith',
            self::HYGIEA => 'Hygiea',
            self::APOLLO => 'Apollo',
            self::ICARUS => 'Icarus',
            self::ATEN => 'Aten',
            self::CRUITHNE => 'Cruithne',
            self::POSEIDON => 'Poseidon',
            self::QUAOAR => 'Quaoar',
            self::HIDALGO => 'Hidalgo',
            self::NESSUS => 'Nessus',
            self::HEKATE => 'Hekate',
            self::NEMESIS => 'Nemesis',
            self::FORTUNA => 'Fortuna',
            self::URANIA => 'Urania',
            self::HEKTOR => 'Hektor',
            self::CHAOS => 'Chaos',
            self::SEDNA => 'Sedna',
            self::ERIS => 'Eris',
            self::ORCUS => 'Orcus',
            self::VARUNA => 'Varuna',
        };
    }
}
