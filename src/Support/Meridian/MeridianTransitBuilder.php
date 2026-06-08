<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Meridian;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\MeridianTransitCollection;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\MeridianGeoPositionNotSetException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

final class MeridianTransitBuilder
{
    use ResolvesSwissephEnvironment;

    private PlanetBody $body = PlanetBody::SUN;

    /** Geographic position is REQUIRED for -metr. Null until at() is called. */
    private ?float $geoLon = null;

    private ?float $geoLat = null;

    private float $geoElev = 0.0;

    private ?Carbon $from = null;

    private int $count = 1;

    private bool $backward = false;

    public function __construct(
        private readonly ?SwissephExecutor $executor = null,
        private readonly ?MeridianTransitParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
    }

    public function forBody(PlanetBody $body): self
    {
        $this->body = $body;

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

    public function backward(): self
    {
        $this->backward = true;

        return $this;
    }

    public function build(): SwissephCommand
    {
        if ($this->geoLon === null || $this->geoLat === null) {
            throw MeridianGeoPositionNotSetException::make();
        }

        $args = [
            $this->epheDirArg(),
            'metr',
            'p'.$this->body->value,
            'geopos'.$this->fmt($this->geoLon)
                .','.$this->fmt($this->geoLat)
                .','.$this->fmt($this->geoElev),
        ];

        if ($this->from !== null) {
            $args[] = 'b'.$this->from->format('d.m.Y');
        }

        $args[] = 'n'.$this->count;

        if ($this->backward) {
            $args[] = 'bwd';
        }

        foreach ($this->ephOptionArgs() as $opt) {
            $args[] = $opt;
        }

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $args,
        );
    }

    public function get(): MeridianTransitCollection
    {
        $command = $this->build();

        $lines = ($this->executor ?? app(SwissephExecutor::class))
            ->run($command, ['geo. long']);

        return ($this->parser ?? app(MeridianTransitParser::class))
            ->parse($lines, $this->body);
    }

    public function getCliCommand(): string
    {
        return $this->build()->toCliString();
    }
}
