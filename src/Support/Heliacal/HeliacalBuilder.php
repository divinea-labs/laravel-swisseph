<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Heliacal;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\HeliacalCollection;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\HeliacalGeoPositionNotSetException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

final class HeliacalBuilder
{
    use ResolvesSwissephEnvironment;

    /** Star target — emitted as `-pf -xf<name>`. Mutually exclusive with $body. */
    private ?string $star = null;

    /** Planet target — emitted as `-p<value>`. Mutually exclusive with $star. */
    private ?PlanetBody $body = null;

    /** Geographic position is REQUIRED for -hev. Null until at() is called. */
    private ?float $geoLon = null;

    private ?float $geoLat = null;

    private float $geoElev = 0.0;

    private ?Carbon $from = null;

    private int $count = 1;

    private ?string $atmosphericModel = null;

    private ?string $observerModel = null;

    private ?string $opticalModel = null;

    public function __construct(
        private readonly ?SwissephExecutor $executor = null,
        private readonly ?HeliacalParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
    }

    public function forBody(PlanetBody $body): self
    {
        $this->body = $body;
        $this->star = null;

        return $this;
    }

    public function forStar(string $name): self
    {
        $this->star = $name;
        $this->body = null;

        return $this;
    }

    public function at(float $lon, float $lat, float $elev = 0.0): self
    {
        $this->geoLon = $lon;
        $this->geoLat = $lat;
        $this->geoElev = $elev;

        return $this;
    }

    public function from(Carbon|string $date): self
    {
        $this->from = $date instanceof Carbon
            ? $date->copy()->utc()
            : Carbon::parse($date, 'UTC');

        return $this;
    }

    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function withAtmosphere(float $pressure, float $temp, float $humidity, float $visibility): self
    {
        $this->atmosphericModel = 'at'.$this->fmt($pressure).','.$this->fmt($temp)
            .','.$this->fmt($humidity).','.$this->fmt($visibility);

        return $this;
    }

    public function withObserver(float $age, float $sn): self
    {
        $this->observerModel = 'obs'.$this->fmt($age).','.$this->fmt($sn);

        return $this;
    }

    public function withOptics(
        float $age,
        float $sn,
        bool $binocular,
        float $magnification,
        float $diameter,
        float $transmission
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

    public function build(): SwissephCommand
    {
        if ($this->geoLon === null || $this->geoLat === null) {
            throw HeliacalGeoPositionNotSetException::make();
        }

        $args = [
            $this->epheDirArg(),
            'hev',
        ];

        if ($this->star !== null) {
            $args[] = 'pf';
            $args[] = 'xf'.$this->star;
        } elseif ($this->body !== null) {
            $args[] = 'p'.$this->body->value;
        }

        $args[] = 'geopos'.$this->fmt($this->geoLon)
            .','.$this->fmt($this->geoLat)
            .','.$this->fmt($this->geoElev);

        if ($this->from !== null) {
            $args[] = 'b'.$this->from->format('d.m.Y');
        }

        $args[] = 'n'.$this->count;

        if ($this->atmosphericModel !== null) {
            $args[] = $this->atmosphericModel;
        }
        if ($this->observerModel !== null) {
            $args[] = $this->observerModel;
        }
        if ($this->opticalModel !== null) {
            $args[] = $this->opticalModel;
        }

        foreach ($this->ephOptionArgs() as $opt) {
            $args[] = $opt;
        }

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $args,
        );
    }

    public function get(): HeliacalCollection
    {
        $command = $this->build();

        $lines = ($this->executor ?? app(SwissephExecutor::class))
            ->run($command, ['geo. long']);

        return ($this->parser ?? app(HeliacalParser::class))->parse($lines);
    }

    public function getCliCommand(): string
    {
        return $this->build()->toCliString();
    }
}
