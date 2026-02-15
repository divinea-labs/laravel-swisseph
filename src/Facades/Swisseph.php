<?php

namespace DivineaLabs\Swisseph\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \DivineaLabs\Swisseph\Swisseph
 *
 * @method static \DivineaLabs\Swisseph\Swisseph withEphOptions(\DivineaLabs\Swisseph\Enums\EphOptions|array ...$options)
 * @method static \DivineaLabs\Swisseph\Swisseph setDateTime(\Carbon\Carbon|string $dateTime, string $timeZone = 'UTC')
 * @method static \DivineaLabs\Swisseph\Swisseph setLocation(float $longitude, float $latitude, ?string $place = null, float $elevation = 0)
 * @method static \DivineaLabs\Swisseph\Swisseph setPlace(string $place)
 * @method static \DivineaLabs\Swisseph\Swisseph setObserverPosition(\DivineaLabs\Swisseph\Enums\ObserverPosition $position, ?\DivineaLabs\Swisseph\Enums\PlanetBody $planet = null)
 * @method static \DivineaLabs\Swisseph\Swisseph withSidereal(\DivineaLabs\Swisseph\Enums\Sidereal $sidereal, bool $eclipticPlaneProjection = false, bool $solarSystemProjection = false)
 * @method static \DivineaLabs\Swisseph\Swisseph withCustomSidereal(float $julianDay, float $ayanamsha, bool $ayanamshaInUT = true, bool $eclipticPlaneProjection = false, bool $solarSystemProjection = false)
 * @method static \DivineaLabs\Swisseph\Swisseph selectBodies(\DivineaLabs\Swisseph\Enums\PlanetBodySelection|array|string $bodies)
 * @method static \DivineaLabs\Swisseph\Swisseph withProperties(\DivineaLabs\Swisseph\Enums\AstroProperties|array ...$properties)
 * @method static \DivineaLabs\Swisseph\Swisseph withHouses(?\DivineaLabs\Swisseph\Enums\HouseSystems $system = null)
 * @method static \DivineaLabs\Swisseph\Data\AstroTimeFrame get()
 * @method static string getCliCommand()
 * @method static \DivineaLabs\Swisseph\Swisseph setRiseBody(\DivineaLabs\Swisseph\Enums\PlanetBody $body)
 * @method static \DivineaLabs\Swisseph\Swisseph setDiscMode(\DivineaLabs\Swisseph\Enums\DiscMode $mode)
 * @method static \DivineaLabs\Swisseph\Swisseph withoutRefraction()
 * @method static \DivineaLabs\Swisseph\Swisseph anchorToLocalMidnight()
 * @method static \DivineaLabs\Swisseph\Swisseph searchBackward()
 * @method static \DivineaLabs\Swisseph\Swisseph setAtmosphericModel(float $pressure, float $temp, float $humidity, float $visibility)
 * @method static \DivineaLabs\Swisseph\Swisseph setObserverModel(float $age, float $sn)
 * @method static \DivineaLabs\Swisseph\Swisseph setOpticalModel(float $age, float $sn, bool $binocular, float $magnification, float $diameter, float $transmission)
 * @method static \DivineaLabs\Swisseph\Data\RiseSetResult getRiseSetEvents(?\DivineaLabs\Swisseph\Enums\PlanetBody $body = null, bool $strict = false)
 * @method static \DivineaLabs\Swisseph\Data\RiseSetBatchResult getRiseSetEventsForBodies(array $bodies, bool $strict = false)
 * @method static \DivineaLabs\Swisseph\Data\RiseSetResult getSunEvents(bool $strict = false)
 */
class Swisseph extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'swisseph';
    }
}
