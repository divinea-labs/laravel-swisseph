<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Occultations;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\OccultationCollection;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\OccultationScope;
use DivineaLabs\Swisseph\Enums\PlanetBody;
use DivineaLabs\Swisseph\Exceptions\OccultationTargetNotSetException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

final class OccultationsBuilder
{
    use ResolvesSwissephEnvironment;

    /** Star target — emitted as `-pf -xf<name>`. Mutually exclusive with $body. */
    private ?string $star = null;

    /** Planet target — emitted as `-p<value>`. Mutually exclusive with $star. */
    private ?PlanetBody $body = null;

    private OccultationScope $scope = OccultationScope::GLOBAL;

    /** Geo longitude for local scope. */
    private float $geoLon = 0.0;

    /** Geo latitude for local scope. */
    private float $geoLat = 0.0;

    /** Geo elevation (metres) for local scope. */
    private float $geoElev = 0.0;

    private ?Carbon $from = null;

    private int $count = 1;

    private bool $backward = false;

    public function __construct(
        private readonly ?SwissephExecutor $executor = null,
        private readonly ?OccultationParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
    }

    public function forStar(string $name): self
    {
        $this->star = $name;
        $this->body = null;

        return $this;
    }

    public function forBody(PlanetBody $body): self
    {
        $this->body = $body;
        $this->star = null;

        return $this;
    }

    public function global(): self
    {
        $this->scope = OccultationScope::GLOBAL;

        return $this;
    }

    public function local(float $lon, float $lat, float $elev = 0.0): self
    {
        $this->scope = OccultationScope::LOCAL;
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
        if ($this->star === null && $this->body === null) {
            throw OccultationTargetNotSetException::make();
        }

        $args = [
            $this->epheDirArg(),
            'occult',
        ];

        // Target: star via -pf -xf<name>, planet via -p<value>
        if ($this->star !== null) {
            $args[] = 'pf';
            $args[] = 'xf'.$this->star;
        } else {
            /** @var PlanetBody $body */
            $body = $this->body;
            $args[] = 'p'.$body->value;
        }

        // Scope: local adds -local -geopos<lon>,<lat>,<elev>
        if ($this->scope === OccultationScope::LOCAL) {
            $args[] = 'local';
            $args[] = 'geopos'.$this->fmt($this->geoLon)
                .','.$this->fmt($this->geoLat)
                .','.$this->fmt($this->geoElev);
        }

        // Date window
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

    public function get(): OccultationCollection
    {
        $command = $this->build();

        // Per cross-plan notes §4: local passes ['geo. long'], global passes [].
        // The executor auto-skips the command-echo line.
        $skipPrefixes = $this->scope === OccultationScope::LOCAL ? ['geo. long'] : [];

        $lines = ($this->executor ?? app(SwissephExecutor::class))
            ->run($command, $skipPrefixes);

        return ($this->parser ?? app(OccultationParser::class))
            ->parse($lines, $this->scope);
    }

    public function getCliCommand(): string
    {
        return $this->build()->toCliString();
    }
}
