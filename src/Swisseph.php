<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\AstroTimeFrame;
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\HouseSystems;
use DivineaLabs\Swisseph\Enums\ObserverPosition;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;
use DivineaLabs\Swisseph\Enums\Sidereal;
use DivineaLabs\Swisseph\Support\Command\SwissephCommandBuilder;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Parsing\SwissephParser;

/**
 * Main Swisseph class providing a fluent API for building and executing swisseph commands.
 */
class Swisseph
{
    public function __construct(
        protected SwissephCommandBuilder $builder,
        protected SwissephExecutor $executor,
        protected SwissephParser $parser,
    ) {}

    /**
     * Set ephemeris options for the calculation.
     *
     * @return $this
     */
    public function withEphOptions(EphOptions|array ...$options): self
    {
        $this->builder->withEphOptions(...$options);

        return $this;
    }

    /**
     * Set the date and time for the calculation.
     *
     * @return $this
     */
    public function setDateTime(Carbon|string $dateTime, string $timeZone = 'UTC'): self
    {
        $this->builder->setDateTime($dateTime, $timeZone);

        return $this;
    }

    /**
     * Set the geographic location for the calculation.
     *
     * @return $this
     */
    public function setLocation(float $longitude, float $latitude, ?string $place = null, float $elevation = 0): self
    {
        $this->builder->setLocation($longitude, $latitude, $place, $elevation);

        return $this;
    }

    /**
     * Set the place name for the location.
     *
     * @return $this
     */
    public function setPlace(string $place): self
    {
        $this->builder->setPlace($place);

        return $this;
    }

    /**
     * Set the observer position for the calculation.
     *
     * @return $this
     */
    public function setObserverPosition(ObserverPosition $position, ?PlanetBody $planet = null): self
    {
        $this->builder->setObserverPosition($position, $planet);

        return $this;
    }

    /**
     * Set sidereal settings for the calculation.
     *
     * @return $this
     */
    public function withSidereal(
        Sidereal $sidereal,
        bool $eclipticPlaneProjection = false,
        bool $solarSystemProjection = false
    ): self {
        $this->builder->withSidereal($sidereal, $eclipticPlaneProjection, $solarSystemProjection);

        return $this;
    }

    /**
     * Set custom sidereal settings for the calculation.
     *
     * @return $this
     */
    public function withCustomSidereal(
        float $julianDay,
        float $ayanamsha,
        bool $ayanamshaInUT = true,
        bool $eclipticPlaneProjection = false,
        bool $solarSystemProjection = false
    ): self {
        $this->builder->withCustomSidereal(
            $julianDay,
            $ayanamsha,
            $ayanamshaInUT,
            $eclipticPlaneProjection,
            $solarSystemProjection
        );

        return $this;
    }

    /**
     * Select the planetary bodies to be calculated.
     *
     * @param  PlanetBodySelection|array  $bodies
     * @return $this
     */
    public function selectBodies(PlanetBodySelection|array|string $bodies): self
    {
        $this->builder->selectBodies($bodies);

        return $this;
    }

    /**
     * Set the properties to be calculated for each selected body.
     *
     * @return $this
     *
     * @throws Exceptions\InvalidPropertyPassedException
     */
    public function withProperties(AstroProperties|array ...$properties): self
    {
        $this->builder->withProperties(...$properties);

        return $this;
    }

    /**
     * Set the house system for the calculation.
     *
     * @return $this
     */
    public function withHouses(?HouseSystems $system = null): self
    {
        $this->builder->withHouses($system);

        return $this;
    }

    /**
     * Execute the constructed command and return the AstroTimeFrame result.
     *
     * @throws Exceptions\InvalidPlanetBodyForPlanetocentricCalculationException
     */
    public function get(): AstroTimeFrame
    {
        // 1. Build command DTO
        $command = $this->builder->build();
        // 2. Run process
        $outputLines = $this->executor->run($command);

        // 3. Parse output to AstroTimeFrame (Spatie Data)
        return $this->parser->parse($outputLines, $this->builder);
    }

    /**
     * Get the constructed CLI command as a string.
     *
     * @throws Exceptions\InvalidPlanetBodyForPlanetocentricCalculationException
     */
    public function getCliCommand(): string
    {
        // Build command DTO
        $command = $this->builder->build();

        // Return command as string
        return $command->toCliString();
    }
}
