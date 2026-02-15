<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\AstroTimeFrame;
use DivineaLabs\Swisseph\Data\RiseSetBatchResult;
use DivineaLabs\Swisseph\Data\RiseSetResult;
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Enums\DiscMode;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\HouseSystems;
use DivineaLabs\Swisseph\Enums\ObserverPosition;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;
use DivineaLabs\Swisseph\Enums\Sidereal;
use DivineaLabs\Swisseph\Exceptions\RiseSetNotFoundException;
use DivineaLabs\Swisseph\Support\Command\SwissephCommandBuilder;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Parsing\SwissephParser;
use DivineaLabs\Swisseph\Support\Rising\RiseCommandBuilder;
use DivineaLabs\Swisseph\Support\Rising\RiseParser;

/**
 * Main Swisseph class providing a fluent API for building and executing swisseph commands.
 */
class Swisseph
{
    /** Default body for getRiseSetEvents() when no body argument is given. */
    private PlanetBody $_riseBody = PlanetBody::SUN;

    public function __construct(
        protected SwissephCommandBuilder $builder,
        protected SwissephExecutor $executor,
        protected SwissephParser $parser,
        protected RiseCommandBuilder $riseCommandBuilder,
        protected RiseParser $riseParser,
    ) {}

    /**
     * Set ephemeris options for the calculation.
     * Delegates to both the position pipeline builder and the rise/set pipeline builder.
     *
     * @return $this
     */
    public function withEphOptions(EphOptions|array ...$options): self
    {
        $this->builder->withEphOptions(...$options);
        $this->riseCommandBuilder->withEphOptions(...$options);

        return $this;
    }

    /**
     * Set the date and time for the calculation.
     * Delegates to both the position pipeline builder and the rise/set pipeline builder.
     * 'UTC' is mapped to null (Mode A) before forwarding to RiseCommandBuilder.
     *
     * @return $this
     */
    public function setDateTime(Carbon|string $dateTime, string $timeZone = 'UTC'): self
    {
        $this->builder->setDateTime($dateTime, $timeZone);

        $riseTimezone = ($timeZone === 'UTC') ? null : $timeZone;
        $this->riseCommandBuilder->setDateTime($dateTime, $riseTimezone);

        return $this;
    }

    /**
     * Set the geographic location for the calculation.
     * Delegates to both the position pipeline builder and the rise/set pipeline builder.
     *
     * @return $this
     */
    public function setLocation(float $longitude, float $latitude, ?string $place = null, float $elevation = 0): self
    {
        $this->builder->setLocation($longitude, $latitude, $place, $elevation);
        $this->riseCommandBuilder->setLocation($longitude, $latitude, (float) $elevation);

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

    // -------------------------------------------------------------------------
    // Rise / Set pipeline
    // -------------------------------------------------------------------------

    /**
     * Set the default body for getRiseSetEvents() calls.
     * Does NOT affect selectBodies() which is for the position pipeline.
     *
     * @return $this
     */
    public function setRiseBody(PlanetBody $body): self
    {
        $this->_riseBody = $body;

        return $this;
    }

    /**
     * Set disc mode for rise/set calculation.
     *
     * @return $this
     */
    public function setDiscMode(DiscMode $mode): self
    {
        $this->riseCommandBuilder->setDiscMode($mode);

        return $this;
    }

    /**
     * Disable atmospheric refraction correction for rise/set calculation.
     *
     * @return $this
     */
    public function withoutRefraction(): self
    {
        $this->riseCommandBuilder->withoutRefraction();

        return $this;
    }

    /**
     * Mode B only: anchor the CLI window start to UTC time of local midnight.
     * No-op in Mode A.
     *
     * @return $this
     */
    public function anchorToLocalMidnight(): self
    {
        $this->riseCommandBuilder->anchorToLocalMidnight();

        return $this;
    }

    /**
     * Reverse swetest search direction for rise/set calculation.
     *
     * @return $this
     */
    public function searchBackward(): self
    {
        $this->riseCommandBuilder->searchBackward();

        return $this;
    }

    /**
     * Set atmospheric model parameters.
     * Emitted as at{pressure},{temp},{humidity},{visibility}.
     *
     * @return $this
     */
    public function setAtmosphericModel(
        float $pressure, float $temp, float $humidity, float $visibility
    ): self {
        $this->riseCommandBuilder->setAtmosphericModel($pressure, $temp, $humidity, $visibility);

        return $this;
    }

    /**
     * Set observer model parameters.
     * Emitted as obs{age},{sn}.
     *
     * @return $this
     */
    public function setObserverModel(float $age, float $sn): self
    {
        $this->riseCommandBuilder->setObserverModel($age, $sn);

        return $this;
    }

    /**
     * Set optical instrument model parameters.
     * Emitted as opt{age},{sn},{binocular},{magnification},{diameter},{transmission}.
     *
     * @return $this
     */
    public function setOpticalModel(
        float $age, float $sn, bool $binocular,
        float $magnification, float $diameter, float $transmission
    ): self {
        $this->riseCommandBuilder->setOpticalModel(
            $age, $sn, $binocular, $magnification, $diameter, $transmission
        );

        return $this;
    }

    /**
     * Compute rise/set events for a single body.
     *
     * When $body is null (default), uses the body previously set via setRiseBody()
     * (or Sun if setRiseBody() was never called). When $body is explicit, it
     * overrides setRiseBody() for this call only — stored state is unchanged.
     */
    public function getRiseSetEvents(?PlanetBody $body = null, bool $strict = false): RiseSetResult
    {
        $resolvedBody = $body ?? $this->_riseBody;

        [$command, $query] = $this->riseCommandBuilder->buildWithQuery($resolvedBody);
        $lines = $this->executor->run($command);
        $result = $this->riseParser->parse($lines, $query);

        if ($strict && $result->events === []) {
            throw $query->isModeB()
                ? RiseSetNotFoundException::forLocalDate($resolvedBody, $query->localDate, $query->timezone)
                : RiseSetNotFoundException::forUtcDate($resolvedBody, $query->utcDate);
        }

        return $result;
    }

    /**
     * Compute rise/set events for multiple bodies.
     * Runs one swetest invocation per body — the CLI supports only one body at a time.
     * setRiseBody() has no effect on this method; all bodies are taken from $bodies.
     *
     * @param  PlanetBody[]  $bodies
     */
    public function getRiseSetEventsForBodies(array $bodies, bool $strict = false): RiseSetBatchResult
    {
        $results = [];

        foreach ($bodies as $body) {
            [$command, $query] = $this->riseCommandBuilder->buildWithQuery($body);
            $lines = $this->executor->run($command);
            $result = $this->riseParser->parse($lines, $query);

            if ($strict && $result->events === []) {
                throw $query->isModeB()
                    ? RiseSetNotFoundException::forLocalDate($body, $query->localDate, $query->timezone)
                    : RiseSetNotFoundException::forUtcDate($body, $query->utcDate);
            }

            $results[$body->value] = $result;
        }

        return new RiseSetBatchResult(results: $results);
    }

    /**
     * Convenience alias — zero-argument shorthand for Sun rise/set.
     * Equivalent to getRiseSetEvents(PlanetBody::SUN, $strict).
     */
    public function getSunEvents(bool $strict = false): RiseSetResult
    {
        return $this->getRiseSetEvents(PlanetBody::SUN, $strict);
    }

    // -------------------------------------------------------------------------
    // Position pipeline
    // -------------------------------------------------------------------------

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
