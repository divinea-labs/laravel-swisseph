<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Positions;

use DivineaLabs\Swisseph\Data\AstroTimeFrame;
use DivineaLabs\Swisseph\Data\AstroTimeSeries;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Enums\ComputedValue;
use DivineaLabs\Swisseph\Enums\HouseSystems;
use DivineaLabs\Swisseph\Enums\ObserverPosition;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;
use DivineaLabs\Swisseph\Enums\Sidereal;
use DivineaLabs\Swisseph\Exceptions\InvalidPlanetBodyForPlanetocentricCalculationException;
use DivineaLabs\Swisseph\Exceptions\InvalidPlanetBodySelectionException;
use DivineaLabs\Swisseph\Exceptions\InvalidPropertyPassedException;
use DivineaLabs\Swisseph\Exceptions\InvalidStepCountException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

class PositionsBuilder
{
    use ResolvesSwissephEnvironment;

    /** @var array<int, string> */
    private array $bodies = [];

    /**
     * Extra body-target arguments emitted verbatim after -p (e.g. xfSirius, xs433, xv1).
     *
     * @var array<int, string>
     */
    private array $bodyTargetArgs = [];

    /** @var AstroProperties[] */
    private array $defaultProperties = [];

    /** @var array<string, AstroProperties> */
    private array $customProperties = [];

    private float $longitude = -0.001545;

    private float $latitude = 51.477928;

    private float $elevation = 0.0;

    private string $place = 'Greenwich';

    private ?ObserverPosition $observerPosition = null;

    private ?PlanetBody $planetocentricPlanet = null;

    private ?string $siderealOption = null;

    private ?HouseSystems $houseSystem = null;

    /**
     * Relative-ephemeris mode argument: 'd<code>' (differential) or 'D<code>' (midpoint),
     * where <code> is the swetest selection code of the reference body. A single slot makes
     * differential and midpoint mutually exclusive — the last call wins.
     */
    private ?string $relativeMode = null;

    private ?int $stepCount = null;

    private ?string $stepSize = null;

    protected ?SwissephExecutor $executor = null;

    protected ?PositionsParser $parser = null;

    public function __construct()
    {
        $this->bootSwissephEnvironment();

        // Default properties always included
        $this->defaultProperties = [
            AstroProperties::PLANET_INDEX,
            AstroProperties::PLANET_NAME,
            AstroProperties::LONGITUDE_DECIMAL,
            AstroProperties::SPEED_LONGITUDE_DECIMAL,
        ];
    }

    /**
     * Execute the command and return parsed AstroTimeFrame.
     */
    public function get(): AstroTimeFrame
    {
        $command = $this->build();
        $lines = ($this->executor ?? app(SwissephExecutor::class))->run($command);

        return ($this->parser ?? app(PositionsParser::class))->parse($lines, $this);
    }

    /**
     * Return the CLI command string without executing.
     */
    public function getCliCommand(): string
    {
        return $this->build()->toCliString();
    }

    /**
     * Function to set location for the calculation.
     *
     * @return $this
     */
    public function setLocation(float $lon, float $lat, ?string $place = null, float $elevation = 0.0): self
    {
        $this->longitude = $lon;
        $this->latitude = $lat;
        $this->elevation = $elevation;
        if ($place !== null) {
            $this->place = $place;
        }

        return $this;
    }

    /**
     * Function to set place name for the calculation.
     *
     * @return $this
     */
    public function setPlace(string $place): self
    {
        $this->place = $place;

        return $this;
    }

    /**
     * Select a fixed star by catalog name (-pf -xf<name>).
     *
     * The result row's name column carries the catalog name (e.g. "Sirius,alCMa").
     *
     * @return $this
     */
    public function selectFixedStar(string $name): self
    {
        $name = trim($name);

        if ($name === '') {
            throw InvalidPlanetBodySelectionException::invalidValue($name);
        }

        $this->bodies = [PlanetBodySelection::FIXED_STAR->value];
        $this->bodyTargetArgs = ['xf'.$name];

        return $this;
    }

    /**
     * Select one or more output-only computed values (Sidereal Time, Delta T,
     * Ecl. Obliquity, Ayanamsha, Time equation). They are appended to the -p
     * selector (e.g. -pqo). Ayanamsha additionally requires withSidereal().
     *
     * @return $this
     */
    public function selectComputedValue(ComputedValue ...$values): self
    {
        if ($values === []) {
            throw InvalidPlanetBodySelectionException::invalidValue('');
        }

        $this->bodies = array_map(
            static fn (ComputedValue $v): string => $v->value,
            $values,
        );
        $this->bodyTargetArgs = [];

        return $this;
    }

    /**
     * Select an asteroid by its MPC number (-ps -xs<number>).
     *
     * @return $this
     */
    public function selectAsteroid(int $mpcNumber): self
    {
        if ($mpcNumber <= 0) {
            throw InvalidPlanetBodySelectionException::invalidValue((string) $mpcNumber);
        }

        $this->bodies = [PlanetBodySelection::ASTEROID->value];
        $this->bodyTargetArgs = ['xs'.$mpcNumber];

        return $this;
    }

    /**
     * Select a planetary moon by its swetest number (-pv -xv<number>).
     *
     * Thin selector: reuses the standard position-row parser. No captured unit
     * fixture exists yet for -pv output — shape is asserted by
     * PositionsMoonsIntegrationTest (self-skipping). When that test runs green
     * against a real binary, the captured block SHOULD be promoted into a
     * tests/Fixtures/swetest-moon-<n>.txt fixture and a unit parser test added.
     * SP3: moon unit fixture deferred to integration capture — see PositionsMoonsIntegrationTest.
     *
     * @return $this
     */
    public function selectMoon(int $number): self
    {
        if ($number <= 0) {
            throw InvalidPlanetBodySelectionException::invalidValue((string) $number);
        }

        $this->bodies = [PlanetBodySelection::PLANETARY_MOON->value];
        $this->bodyTargetArgs = ['xv'.$number];

        return $this;
    }

    /**
     * Function to select planet bodies for the calculation.
     *
     * @return $this
     */
    public function selectBodies(PlanetBodySelection|array|string $bodies): self
    {
        $this->bodies = [];
        $this->bodyTargetArgs = []; // reset any -xf/-xs/-xv target from a prior fixed-star/asteroid/moon selection

        if ($bodies instanceof PlanetBodySelection) {
            $this->bodies[] = $bodies->value;

            return $this;
        }

        if (is_array($bodies)) {
            foreach ($bodies as $body) {
                if (! $body instanceof PlanetBodySelection) {
                    throw InvalidPlanetBodySelectionException::notEnum($body);
                }
                $this->bodies[] = $body->value;
            }

            return $this;
        }

        $enum = PlanetBodySelection::tryFrom($bodies);
        if (! $enum) {
            throw InvalidPlanetBodySelectionException::invalidValue($bodies);
        }

        $this->bodies[] = $enum->value;

        return $this;
    }

    /**
     * Function to add custom properties for the calculation.
     *
     * @return $this
     *
     * @throws InvalidPropertyPassedException
     */
    public function withProperties(...$properties): self
    {
        $flat = [];
        foreach ($properties as $p) {
            if (is_array($p)) {
                foreach ($p as $item) {
                    if (! $item instanceof AstroProperties) {
                        throw new InvalidPropertyPassedException($item);
                    }
                    $flat[] = $item;
                }
            } else {
                if (! $p instanceof AstroProperties) {
                    throw new InvalidPropertyPassedException($p);
                }
                $flat[] = $p;
            }
        }

        foreach ($flat as $property) {
            if (in_array($property, $this->defaultProperties, true)) {
                continue; // ignore duplicates
            }
            $this->customProperties[$property->value] = $property;
        }

        return $this;
    }

    /**
     * Function to include house calculations.
     *
     * @return $this
     */
    public function withHouses(?HouseSystems $system = null): self
    {
        $default = config('swisseph.default_house_system');

        $this->houseSystem = $system
            ?? ($default instanceof HouseSystems ? $default : HouseSystems::tryFrom((string) $default))
            ?? HouseSystems::PLACIDUS;

        $this->customProperties[AstroProperties::HOUSE_POSITION_DEGREES->value]
            = AstroProperties::HOUSE_POSITION_DEGREES;

        $this->customProperties[AstroProperties::HOUSE_POSITION_DEGREES_DECIMAL->value]
            = AstroProperties::HOUSE_POSITION_DEGREES_DECIMAL;

        $this->customProperties[AstroProperties::HOUSE_NUMBER_DECIMAL->value]
            = AstroProperties::HOUSE_NUMBER_DECIMAL;

        return $this;
    }

    /**
     * Function to set observer position for the calculation.
     *
     * @return $this
     */
    public function setObserverPosition(ObserverPosition $position, ?PlanetBody $planet = null): self
    {
        $this->observerPosition = $position;
        $this->planetocentricPlanet = $planet;

        return $this;
    }

    /**
     * Function to set sidereal calculation options.
     *
     * @return $this
     */
    public function withSidereal(
        Sidereal $sidereal,
        bool $eclipticPlaneProjection = false,
        bool $solarSystemProjection = false
    ): self {
        if ($eclipticPlaneProjection) {
            $this->siderealOption = 'sidt0'.$sidereal->value;
        } elseif ($solarSystemProjection) {
            $this->siderealOption = 'sidsp'.$sidereal->value;
        } else {
            $this->siderealOption = 'sid'.$sidereal->value;
        }

        return $this;
    }

    /**
     * Function to set custom sidereal calculation options.
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
        $options = [];

        if ($ayanamshaInUT) {
            $options[] = 'jdisut';
        }

        if ($eclipticPlaneProjection || $solarSystemProjection) {
            $options[] = $eclipticPlaneProjection ? 'eclt0' : 'ssyplane';
        }

        $this->siderealOption =
            'sidudef'.implode(',', array_merge([$julianDay, $ayanamsha], $options));

        return $this;
    }

    /**
     * Differential ephemeris: print the longitude/latitude difference between the reference
     * body and each selected body. Emits -d<code> where <code> is the reference's swetest
     * selection code (e.g. Sun -> '0' -> -d0, Chiron -> 'D' -> -dD). The output name column
     * becomes "A-B" (e.g. "Mer-Sun").
     *
     * NOTE: the reference is a PlanetBodySelection (swetest selection code), NOT a PlanetBody
     * (internal number). swetest reads -d/-D references the same way as -p, so -d15 would be
     * parsed as code '1' then '5' (Moon...), not "body 15" — using the selection code is the
     * only correct mapping for asteroids/Chiron/etc.
     *
     * Mutually exclusive with midpointTo(): the last call wins.
     */
    public function differentialTo(PlanetBodySelection $reference): self
    {
        $this->relativeMode = 'd'.$reference->value;

        return $this;
    }

    /**
     * Midpoint ephemeris: print the midpoint between the reference body and each selected body.
     * Emits -D<code> (e.g. Chiron -> 'D' -> -DD). The output name column becomes "A/B"
     * (e.g. "Sat/Chi"). See differentialTo() for why the reference is a PlanetBodySelection.
     *
     * Mutually exclusive with differentialTo(): the last call wins.
     */
    public function midpointTo(PlanetBodySelection $reference): self
    {
        $this->relativeMode = 'D'.$reference->value;

        return $this;
    }

    /**
     * Request a batch ephemeris run: emit N time-stepped frames in ONE process.
     *
     * @param  int  $count  Number of frames (`-n<count>`); must be >= 1.
     * @param  string|null  $stepSize  Raw swetest step token (`-s<stepSize>`):
     *                                 bare = days (`1`, `6`), `m` = minutes (`15m`; `360m` = 6h —
     *                                 there is NO hour suffix), `mo` = months (`3mo`),
     *                                 `y` = years (`10y`), `s` = seconds (`1s`). Sub-day works.
     *
     * @throws InvalidStepCountException
     */
    public function steps(int $count, ?string $stepSize = null): self
    {
        if ($count < 1) {
            throw InvalidStepCountException::mustBePositive($count);
        }

        $this->stepCount = $count;
        $this->stepSize = $stepSize;

        // Each frame must be timestamp-prefixed so the parser can group rows.
        // T must be the FIRST element of the emitted -f sequence.
        // If the caller already placed T in $customProperties (via withProperties() before
        // steps()), remove it from there so it does not end up appended after the defaults.
        unset($this->customProperties[AstroProperties::DATE_FORMAT_DD_MM_YYYY->value]);

        // Now ensure T leads $defaultProperties (prepend if not already first).
        if (($this->defaultProperties[0] ?? null) !== AstroProperties::DATE_FORMAT_DD_MM_YYYY) {
            // Remove any stale occurrence deeper in defaultProperties (defensive).
            $this->defaultProperties = array_values(array_filter(
                $this->defaultProperties,
                static fn (AstroProperties $p) => $p !== AstroProperties::DATE_FORMAT_DD_MM_YYYY,
            ));
            array_unshift($this->defaultProperties, AstroProperties::DATE_FORMAT_DD_MM_YYYY);
        }

        return $this;
    }

    /**
     * Execute the command and return a time series of N frames (batch ephemeris).
     */
    public function getSeries(): AstroTimeSeries
    {
        $command = $this->build();
        $lines = ($this->executor ?? app(SwissephExecutor::class))->run($command);

        return ($this->parser ?? app(PositionsParser::class))->parseSeries($lines, $this);
    }

    public function build(): SwissephCommand
    {
        $args = [
            $this->epheDirArg(),
            ...$this->ephOptionArgs(),
            $this->dateArg(),
            $this->utTimeArg(),
            $this->buildBodies(),
            ...$this->bodyTargetArgs,
        ];

        if ($this->relativeMode !== null) {
            $args[] = $this->relativeMode;
        }

        // SP4: batch ephemeris steps
        if ($this->stepCount !== null) {
            $args[] = 'n'.$this->stepCount;

            if ($this->stepSize !== null && $this->stepSize !== '') {
                $args[] = 's'.$this->stepSize;
            }
        }

        if ($houses = $this->buildHouses()) {
            $args[] = $houses;
        }

        if ($this->siderealOption) {
            $args[] = $this->siderealOption;
        }

        if ($observer = $this->buildObserver()) {
            $args[] = $observer;
        }

        $args[] = $this->buildPropertiesSequence();
        $args[] = 'gPPP';
        $args[] = 'head';

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $args
        );
    }

    /**
     * Getter for place.
     */
    public function getPlace(): string
    {
        return $this->place;
    }

    /**
     * Getter for latitude.
     */
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    /**
     * Getter for longitude.
     */
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    /**
     * Getter for house system.
     */
    public function getHouseSystem(): ?HouseSystems
    {
        return $this->houseSystem;
    }

    /**
     * Getter for properties sequence.
     */
    public function getProperties(): array
    {
        return array_merge(
            $this->defaultProperties,
            array_values($this->customProperties)
        );
    }

    /**
     * Function to build planet bodies argument.
     */
    private function buildBodies(): string
    {
        if (empty($this->bodies)) {
            $this->bodies[] = PlanetBodySelection::DEFAULT_FACTORS->value;
        }

        return 'p'.implode('', $this->bodies);
    }

    /**
     * Function to build properties sequence argument.
     */
    private function buildPropertiesSequence(): string
    {
        $sequence = array_merge(
            $this->defaultProperties,
            array_values($this->customProperties)
        );

        return 'f'.implode('', array_map(fn ($p) => $p->value, $sequence));
    }

    /**
     * Function to build houses argument.
     */
    private function buildHouses(): ?string
    {
        if (! $this->houseSystem) {
            return null;
        }

        return 'house'.$this->fmt($this->longitude).','.$this->fmt($this->latitude).','.$this->houseSystem->value;
    }

    /**
     * Function to build observer position argument.
     *
     * @throws InvalidPlanetBodyForPlanetocentricCalculationException
     */
    private function buildObserver(): ?string
    {
        if ($this->observerPosition === null) {
            return null;
        }

        return match ($this->observerPosition) {
            ObserverPosition::TOPOCENTRIC => "topo{$this->fmt($this->longitude)},{$this->fmt($this->latitude)},{$this->fmt($this->elevation)}",

            ObserverPosition::PLANETOCENTRIC => $this->planetocentricPlanet
                ? 'pc'.$this->planetocentricPlanet->value
                : throw new InvalidPlanetBodyForPlanetocentricCalculationException,

            default => $this->observerPosition->value,
        };
    }
}
