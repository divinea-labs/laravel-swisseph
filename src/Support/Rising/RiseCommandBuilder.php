<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Rising;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\DiscMode;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\InvalidEphOptionPassedException;

final class RiseCommandBuilder
{
    // Defaults — match swetest built-in defaults where applicable
    private float    $longitude  = -0.001545;   // Greenwich
    private float    $latitude   = 51.477928;
    private float    $elevation  = 0.0;
    private DiscMode $discMode   = DiscMode::BOTTOM;
    private bool     $noRefraction          = false;
    private bool     $anchorToLocalMidnight = false;
    private bool     $searchBackward        = false;
    private int      $windowDays = 3;
    private float    $stepDays   = 1.0;

    /** @var array<string, EphOptions> de-duped by value, same as SwissephCommandBuilder */
    private array    $ephOptions = [];

    private ?string  $atmosphericModel = null;  // "at{press},{temp},{rhum},{visr}" — emitted when set
    private ?string  $observerModel    = null;  // "obs{age},{sn}"                  — emitted when set
    private ?string  $opticalModel     = null;  // "opt{age},{sn},{bin},{m},{d},{t}" — emitted when set

    // Set by setDateTime()
    private ?Carbon  $windowStartUtc = null;
    private ?string  $timezone       = null;    // null = Mode A
    private ?string  $localDate      = null;    // null in Mode A
    private string   $utcDate        = '';      // always set before build

    public function __construct()
    {
        $exe = (string) config('swisseph.executable', '');
        $dir = str_replace('\\', '/', (string) config('swisseph.ephemeris_dir', ''));
        $dir = rtrim($dir, '/');
        $dir = '/'.ltrim($dir, '/');

        $this->edirToken = 'edir'.$dir;
        $this->executable = $exe;

        // Mirror SwissephCommandBuilder: load default eph options from config
        foreach ((array) config('swisseph.eph_options', []) as $v) {
            if ($v instanceof EphOptions) {
                $this->ephOptions[$v->value] = $v;
            }
        }
    }

    private string $edirToken  = '';
    private string $executable = '';

    /**
     * Set the date/time for the calculation window.
     *
     * - Mode A (default): pass a UTC string or Carbon, or omit timezone / pass 'UTC'.
     * - Mode B: pass any non-UTC IANA timezone string.
     *
     * The 'UTC' → null mapping is handled by Swisseph::setDateTime before delegating here;
     * RiseCommandBuilder itself accepts a nullable $timezone where null = Mode A.
     */
    public function setDateTime(Carbon|string $dateTime, ?string $timezone = null): self
    {
        $tz = ($timezone === null || $timezone === 'UTC') ? null : $timezone;

        if ($tz !== null) {
            // Mode B
            $carbon = $dateTime instanceof Carbon
                ? $dateTime->copy()->setTimezone($tz)
                : Carbon::parse($dateTime, $tz);

            $this->localDate      = $carbon->format('Y-m-d');
            $this->timezone       = $tz;
            $midnight             = Carbon::createMidnightDate(
                $carbon->year, $carbon->month, $carbon->day, $tz
            )->utc();
            $this->windowStartUtc = $midnight;
            $this->utcDate        = $midnight->format('Y-m-d');
        } else {
            // Mode A
            $carbon = $dateTime instanceof Carbon
                ? $dateTime->copy()->utc()
                : Carbon::parse($dateTime, 'UTC');

            $this->localDate      = null;
            $this->timezone       = null;
            $this->windowStartUtc = $carbon->copy()->startOfDay();
            $this->utcDate        = $carbon->format('Y-m-d');
        }

        return $this;
    }

    /**
     * Set geographic location.
     */
    public function setLocation(float $lon, float $lat, float $elevation = 0.0): self
    {
        $this->longitude  = $lon;
        $this->latitude   = $lat;
        $this->elevation  = $elevation;

        return $this;
    }

    /**
     * Set disc mode (bottom limb, centre, or Hindu).
     */
    public function setDiscMode(DiscMode $mode): self
    {
        $this->discMode = $mode;

        return $this;
    }

    /**
     * Disable atmospheric refraction correction.
     */
    public function withoutRefraction(): self
    {
        $this->noRefraction = true;

        return $this;
    }

    /**
     * Mode B only: also emit ut{HH:MM:SS} anchored to UTC time of local midnight.
     * No-op in Mode A.
     */
    public function anchorToLocalMidnight(): self
    {
        $this->anchorToLocalMidnight = true;

        return $this;
    }

    /**
     * Reverse swetest search direction. Emits the bwd token.
     */
    public function searchBackward(): self
    {
        $this->searchBackward = true;

        return $this;
    }

    /**
     * Override the search window width (default: 3 days).
     */
    public function setWindowDays(int $days): self
    {
        $this->windowDays = $days;

        return $this;
    }

    /**
     * Override the search step size (default: 1.0 day).
     */
    public function setStepDays(float $days): self
    {
        $this->stepDays = $days;

        return $this;
    }

    /**
     * Add/merge ephemeris options. Mirrors SwissephCommandBuilder::withEphOptions().
     *
     * @throws InvalidEphOptionPassedException
     */
    public function withEphOptions(EphOptions|array ...$options): self
    {
        foreach ($options as $opt) {
            $items = is_array($opt) ? $opt : [$opt];
            foreach ($items as $item) {
                if (! $item instanceof EphOptions) {
                    throw new InvalidEphOptionPassedException($item);
                }
                $this->ephOptions[$item->value] = $item;
            }
        }

        return $this;
    }

    /**
     * Emit at{pressure},{temp},{humidity},{visibility} when set.
     */
    public function setAtmosphericModel(
        float $pressure, float $temp, float $humidity, float $visibility
    ): self {
        $this->atmosphericModel = 'at'.$this->fmt($pressure).','.$this->fmt($temp).','.$this->fmt($humidity).','.$this->fmt($visibility);

        return $this;
    }

    /**
     * Emit obs{age},{sn} when set.
     */
    public function setObserverModel(float $age, float $sn): self
    {
        $this->observerModel = 'obs'.$this->fmt($age).','.$this->fmt($sn);

        return $this;
    }

    /**
     * Emit opt{age},{sn},{binocular},{magnification},{diameter},{transmission} when set.
     */
    public function setOpticalModel(
        float $age, float $sn, bool $binocular,
        float $magnification, float $diameter, float $transmission
    ): self {
        $this->opticalModel = 'opt'
            .$this->fmt($age).','
            .$this->fmt($sn).','
            .($binocular ? '1' : '0').','
            .$this->fmt($magnification).','
            .$this->fmt($diameter).','
            .$this->fmt($transmission);

        return $this;
    }

    /**
     * Build CLI command + query context for the given body.
     * Body is a parameter, not stored state — safe to call N times in a loop.
     *
     * @return array{0: SwissephCommand, 1: RiseQuery}
     */
    public function buildWithQuery(PlanetBody $body = PlanetBody::SUN): array
    {
        // Default to today UTC start-of-day when setDateTime was never called (Mode A)
        if ($this->windowStartUtc === null) {
            $this->windowStartUtc = Carbon::now('UTC')->startOfDay();
            $this->utcDate        = $this->windowStartUtc->format('Y-m-d');
            // $this->timezone and $this->localDate remain null (Mode A)
        }

        $args = [
            $this->edirToken,
            ...$this->buildEphOptions(),
            'p'.$body->value,
            'rise',
            'geopos'.$this->fmt($this->longitude).','.$this->fmt($this->latitude).','.$this->fmt($this->elevation),
            'b'.$this->windowStartUtc->format('d.m.Y'),
        ];

        // Mode B + anchorToLocalMidnight: also emit ut token
        if ($this->timezone !== null && $this->anchorToLocalMidnight) {
            $args[] = 'ut'.$this->windowStartUtc->format('H:i:s');
        }

        $args[] = 'n'.$this->windowDays;
        $args[] = 's'.$this->fmt($this->stepDays);
        $args[] = $this->discMode->value;

        if ($this->noRefraction) {
            $args[] = 'norefrac';
        }

        if ($this->atmosphericModel !== null) {
            $args[] = $this->atmosphericModel;
        }

        if ($this->observerModel !== null) {
            $args[] = $this->observerModel;
        }

        if ($this->opticalModel !== null) {
            $args[] = $this->opticalModel;
        }

        if ($this->searchBackward) {
            $args[] = 'bwd';
        }

        $command = new SwissephCommand(
            executable: $this->executable,
            arguments:  $args,
        );

        $query = new RiseQuery(
            body:                  $body,
            windowStartUtc:        $this->windowStartUtc,
            timezone:              $this->timezone,
            localDate:             $this->localDate,
            utcDate:               $this->utcDate,
            longitude:             $this->longitude,
            latitude:              $this->latitude,
            elevation:             $this->elevation,
            discMode:              $this->discMode,
            noRefraction:          $this->noRefraction,
            anchorToLocalMidnight: $this->anchorToLocalMidnight,
            windowDays:            $this->windowDays,
            stepDays:              $this->stepDays,
            atmosphericModel:      $this->atmosphericModel,
            observerModel:         $this->observerModel,
            opticalModel:          $this->opticalModel,
        );

        return [$command, $query];
    }

    /**
     * Format float to string with up to 8 decimal places, trimming trailing zeros.
     * Identical to SwissephCommandBuilder::fmt().
     */
    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 8, '.', ''), '0'), '.');
    }

    /**
     * Build sorted, de-duped eph option tokens.
     * Mirrors SwissephCommandBuilder::buildEphOptions().
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
