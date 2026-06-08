<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\OrbitalElements;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OrbitalElementsData;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\OrbitalElementsBodyNotSetException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

final class OrbitalElementsBuilder
{
    use ResolvesSwissephEnvironment;

    private ?PlanetBody $body = null;

    private ?Carbon $from = null;

    public function __construct(
        private readonly ?SwissephExecutor $executor = null,
        private readonly ?OrbitalElementsParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
    }

    public function forBody(PlanetBody $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function from(Carbon|string $date): self
    {
        $this->from = $date instanceof Carbon
            ? $date->copy()->utc()
            : Carbon::parse($date, 'UTC');

        return $this;
    }

    public function build(): SwissephCommand
    {
        if ($this->body === null) {
            throw OrbitalElementsBodyNotSetException::make();
        }

        // -orbel computes relative to mean ecliptic J2000 and uses the TT reference
        // date (no -ut: the date is interpreted as Terrestrial Time).
        $args = [
            $this->epheDirArg(),
            'orbel',
            'p'.$this->body->value,
        ];

        if ($this->from !== null) {
            $args[] = 'b'.$this->from->format('d.m.Y');
        }

        foreach ($this->ephOptionArgs() as $opt) {
            $args[] = $opt;
        }

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $args,
        );
    }

    public function get(): OrbitalElementsData
    {
        /** @var PlanetBody $body */
        $body = $this->body ?? throw OrbitalElementsBodyNotSetException::make();

        $command = $this->build();

        $lines = ($this->executor ?? app(SwissephExecutor::class))->run($command);

        return ($this->parser ?? app(OrbitalElementsParser::class))->parse($lines, $body);
    }

    public function getCliCommand(): string
    {
        return $this->build()->toCliString();
    }
}
