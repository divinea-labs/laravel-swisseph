<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Command;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\AstroProperties;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\HouseSystems;
use DivineaLabs\Swisseph\Enums\ObserverPosition;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Enums\PlanetBodySelection;
use DivineaLabs\Swisseph\Enums\Sidereal;
use DivineaLabs\Swisseph\Exceptions\InvalidEphOptionPassedException;
use DivineaLabs\Swisseph\Exceptions\InvalidPlanetBodyForPlanetocentricCalculationException;
use DivineaLabs\Swisseph\Exceptions\InvalidPlanetBodySelectionException;
use DivineaLabs\Swisseph\Exceptions\InvalidPropertyPassedException;

class SwissephCommandBuilder
{
    /** @var array<string, mixed> */
    private array $options = [];

    private Carbon $dateTime;

    /** @var array<int, string> */
    private array $bodies = [];

    /** @var AstroProperties[] */
    private array $defaultProperties = [];

    /** @var array<string, AstroProperties> */
    private array $customProperties = [];

    /** @var array<string, EphOptions> */
    private array $ephOptions = [];

    private float $longitude = -0.001545;

    private float $latitude = 51.477928;

    private float $elevation = 0.0;

    private string $place = 'Greenwich';

    private ?ObserverPosition $observerPosition = null;

    private ?PlanetBody $planetocentricPlanet = null;

    private ?string $siderealOption = null;

    private ?HouseSystems $houseSystem = null;

    public function __construct()
    {
        $exe = (string) config('swisseph.executable', '');
        $dir = str_replace('\\', '/', (string) config('swisseph.ephemeris_dir', ''));
        $dir = rtrim($dir, '/');
        $dir = '/'.ltrim($dir, '/');

        $this->options = [
            'executable' => $exe,
            'ephe_dir' => 'edir'.$dir,
        ];

        // Load eph options from config (array of enums)
        foreach ((array) config('swisseph.eph_options', []) as $v) {
            if ($v instanceof EphOptions) {
                $this->ephOptions[$v->value] = $v; // de-dupe + map
            }
        }

        // Default properties always included
        $this->defaultProperties = [
            AstroProperties::PLANET_INDEX,
            AstroProperties::PLANET_NAME,
            AstroProperties::LONGITUDE_DECIMAL,
            AstroProperties::SPEED_LONGITUDE_DECIMAL,
        ];

        $this->dateTime = Carbon::now()->utc();
    }

    /**
     * Function to set date and time for the calculation.
     *
     * @return $this
     */
    public function setDateTime(Carbon|string $date, string $tz = 'UTC'): self
    {
        $dt = is_string($date) ? Carbon::parse($date, $tz) : $date;
        $this->dateTime = $dt->utc();

        return $this;
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
     * Function to select planet bodies for the calculation.
     *
     * @return $this
     */
    public function selectBodies(PlanetBodySelection|array|string $bodies): self
    {
        $this->bodies = [];

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
     * Function to set ephemeris options for the calculation.
     *
     * @param  array  $options
     * @return $this
     *
     * @throws InvalidEphOptionPassedException
     */
    public function withEphOptions(EphOptions|array ...$options): static
    {
        foreach ($options as $opt) {
            $items = is_array($opt) ? $opt : [$opt];

            foreach ($items as $item) {
                if (! $item instanceof EphOptions) {
                    throw new InvalidEphOptionPassedException($item);
                }

                // de-dupe by CLI value
                $this->ephOptions[$item->value] = $item;
            }
        }

        return $this;
    }

    /**
     * Function to build the final Swisseph command.
     *
     * @throws InvalidPlanetBodyForPlanetocentricCalculationException
     */
    public function build(): SwissephCommand
    {
        $args = [
            $this->options['ephe_dir'],
            ...$this->buildEphOptions(),
            $this->buildDateArgument(),
            $this->buildTimeArgument(),
            $this->buildBodies(),
        ];

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
            executable: $this->options['executable'],
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
     * Getter for date.
     */
    public function getDate(): Carbon
    {
        return $this->dateTime;
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
     * Format float value to string with up to 8 decimal places, trimming trailing zeros.
     */
    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 8, '.', ''), '0'), '.');
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

    private function buildDateArgument(): string
    {
        return 'b'.$this->dateTime->format('d.m.Y');
    }

    private function buildTimeArgument(): string
    {
        return 'ut'.$this->dateTime->format('H:i:s');
    }

    /**
     * Build ephemeris options arguments.
     *
     * @return string[]
     */
    private function buildEphOptions(): array
    {
        $opts = array_values($this->ephOptions);

        usort(
            $opts,
            static fn (EphOptions $a, EphOptions $b) => $a->value <=> $b->value
        );

        return array_map(
            static fn (EphOptions $opt) => $opt->value,
            $opts
        );
    }
}
