<?php

namespace DivineaLabs\Swisseph\Enums;

enum EphOptions: string
{
    // Type of ephemeris
    case SWISS_TYPE = 'eswe';
    case JPL_TYPE = 'ejpl';
    case MOSHIER = 'emos';

    // Reference frame / precession
    case NO_PRECESSION = 'j2000';
    case ICRS = 'icrs';

    // Corrections
    case NO_ABERRATION = 'noaberr';
    case NO_LIGHT_DEFLECTION = 'nodefl';
    case NO_NUTATION = 'nonut';
    case TRUE_POSITIONS = 'true';

    // Path prefix for ephemeris directory
    case EPHEMERIS_PATH = 'edir';
}
