<?php

namespace DivineaLabs\Swisseph\Enums;

/**
 * Curated set of commonly-used fixed stars.
 *
 * The backing value is the Swiss Ephemeris catalog traditional name (the exact
 * string swetest expects after `-xf`). This is a convenience subset — any entry
 * from `ephe/sefstars.txt` may still be passed to the selectors as a raw string.
 */
enum FixedStar: string
{
    case SIRIUS = 'Sirius';
    case CANOPUS = 'Canopus';
    case RIGIL_KENTAURUS = 'Bungula'; // display "Rigil Kentaurus"; catalog name is "Bungula"
    case ARCTURUS = 'Arcturus';
    case VEGA = 'Vega';
    case CAPELLA = 'Capella';
    case RIGEL = 'Rigel';
    case PROCYON = 'Procyon';
    case BETELGEUSE = 'Betelgeuse';
    case ACHERNAR = 'Achernar';
    case ALTAIR = 'Altair';
    case ACRUX = 'Acrux';
    case ALDEBARAN = 'Aldebaran';
    case ANTARES = 'Antares';
    case SPICA = 'Spica';
    case POLLUX = 'Pollux';
    case FOMALHAUT = 'Fomalhaut';
    case DENEB = 'Deneb';
    case REGULUS = 'Regulus';
    case CASTOR = 'Castor';
    case BELLATRIX = 'Bellatrix';
    case ALGOL = 'Algol';
    case ALPHECCA = 'Alphecca';
    case ALCYONE = 'Alcyone';
    case POLARIS = 'Polaris';
    case ALPHARD = 'Alphard';
    case HAMAL = 'Hamal';
    case ALPHERATZ = 'Alpheratz';
    case RASALHAGUE = 'Rasalhague';
    case DENEBOLA = 'Denebola';
    case MENKAR = 'Menkar';
    case ZUBENELGENUBI = 'Zubenelgenubi';
    case ZUBENESHAMALI = 'Zubeneshamali';
    case VINDEMIATRIX = 'Vindemiatrix';
    case MARKAB = 'Markab';
    case SCHEAT = 'Scheat';
    case ALGENIB = 'Algenib';
    case SADALMELEK = 'Sadalmelek';
    case MIRFAK = 'Mirfak';
    case WEZEN = 'Wezen';
    case ALNILAM = 'Alnilam';
    case MIRZAM = 'Mirzam';
    case MIRA = 'Mira';
    case GALACTIC_CENTER = 'Galactic Center';

    /**
     * Human-readable display name. Equal to the catalog value for every star
     * except RIGIL_KENTAURUS, whose catalog name is "Bungula".
     */
    public function getName(): string
    {
        return match ($this) {
            self::RIGIL_KENTAURUS => 'Rigil Kentaurus',
            default => $this->value,
        };
    }
}
