<?php

namespace DivineaLabs\Swisseph\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \DivineaLabs\Swisseph\Swisseph
 *
 * @method static \DivineaLabs\Swisseph\Support\Positions\PositionsBuilder positions()
 * @method static \DivineaLabs\Swisseph\Support\Rising\RisingsBuilder risings()
 * @method static \DivineaLabs\Swisseph\Support\Eclipses\EclipsesBuilder eclipses()
 * @method static \DivineaLabs\Swisseph\Support\Occultations\OccultationsBuilder occultations()
 * @method static \DivineaLabs\Swisseph\Support\Meridian\MeridianTransitBuilder meridianTransits()
 * @method static \DivineaLabs\Swisseph\Support\OrbitalElements\OrbitalElementsBuilder orbitalElements()
 * @method static \DivineaLabs\Swisseph\Support\Heliacal\HeliacalBuilder heliacal()
 */
class Swisseph extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'swisseph';
    }
}
