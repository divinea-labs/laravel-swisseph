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
 */
class Swisseph extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'swisseph';
    }
}
