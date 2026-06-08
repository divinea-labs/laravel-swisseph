<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Eclipses;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Data\EclipseCollection;
use DivineaLabs\Swisseph\Data\SwissephCommand;
use DivineaLabs\Swisseph\Enums\EclipseKind;
use DivineaLabs\Swisseph\Enums\EclipseScope;
use DivineaLabs\Swisseph\Exceptions\EclipseKindNotSetException;
use DivineaLabs\Swisseph\Exceptions\InvalidEclipseFilterException;
use DivineaLabs\Swisseph\Support\Command\SwissephExecutor;
use DivineaLabs\Swisseph\Support\Concerns\ResolvesSwissephEnvironment;

class EclipsesBuilder
{
    use ResolvesSwissephEnvironment;

    private ?EclipseKind $kind = null;

    private EclipseScope $scope = EclipseScope::GLOBAL;

    private float $localLongitude = 0.0;

    private float $localLatitude = 0.0;

    private float $localElevation = 0.0;

    private Carbon $from;

    private int $count = 1;

    private bool $backward = false;

    /** @var list<string> swetest filter flags in declaration order */
    private array $filters = [];

    /** @var list<string> the builder-method name behind each filter flag (for error messages) */
    private array $filterMethods = [];

    public function __construct(
        private readonly ?SwissephExecutor $executor = null,
        private readonly ?EclipseParser $parser = null,
    ) {
        $this->bootSwissephEnvironment();
        $this->from = Carbon::now()->utc();
    }

    public function solar(): static
    {
        $this->kind = EclipseKind::SOLAR;

        return $this;
    }

    public function lunar(): static
    {
        $this->kind = EclipseKind::LUNAR;

        return $this;
    }

    public function global(): static
    {
        $this->scope = EclipseScope::GLOBAL;

        return $this;
    }

    public function local(float $lon, float $lat, float $elev = 0.0): static
    {
        $this->scope = EclipseScope::LOCAL;
        $this->localLongitude = $lon;
        $this->localLatitude = $lat;
        $this->localElevation = $elev;

        return $this;
    }

    public function from(Carbon|string $date): static
    {
        $this->from = is_string($date) ? Carbon::parse($date, 'UTC') : $date->copy()->utc();

        return $this;
    }

    public function count(int $count): static
    {
        $this->count = $count;

        return $this;
    }

    public function backward(): static
    {
        $this->backward = true;

        return $this;
    }

    public function onlyTotal(): static
    {
        return $this->addFilter('total', 'onlyTotal');
    }

    public function onlyPartial(): static
    {
        return $this->addFilter('partial', 'onlyPartial');
    }

    public function onlyAnnular(): static
    {
        return $this->addFilter('annular', 'onlyAnnular');
    }

    public function onlyHybrid(): static
    {
        return $this->addFilter('anntot', 'onlyHybrid');
    }

    public function onlyPenumbral(): static
    {
        return $this->addFilter('penumbral', 'onlyPenumbral');
    }

    public function onlyCentral(): static
    {
        return $this->addFilter('central', 'onlyCentral');
    }

    public function onlyNonCentral(): static
    {
        return $this->addFilter('noncentral', 'onlyNonCentral');
    }

    private function addFilter(string $flag, string $method): static
    {
        $this->filters[] = $flag;
        $this->filterMethods[] = $method;

        return $this;
    }

    public function build(): SwissephCommand
    {
        if ($this->kind === null) {
            throw EclipseKindNotSetException::missing();
        }

        if ($this->kind === EclipseKind::LUNAR && $this->scope === EclipseScope::LOCAL) {
            throw InvalidEclipseFilterException::lunarLocalUnsupported();
        }

        $this->validateFilters($this->kind);

        $arguments = [$this->epheDirArg()];

        foreach ($this->ephOptionArgs() as $opt) {
            $arguments[] = $opt;
        }

        $arguments[] = $this->kind === EclipseKind::SOLAR ? 'solecl' : 'lunecl';

        if ($this->scope === EclipseScope::LOCAL) {
            $arguments[] = 'local';
            $arguments[] = sprintf(
                'geopos%s,%s,%s',
                $this->fmt($this->localLongitude),
                $this->fmt($this->localLatitude),
                $this->fmt($this->localElevation),
            );
        }

        $arguments[] = 'b'.$this->from->format('d.m.Y');
        $arguments[] = 'n'.$this->count;

        if ($this->backward) {
            $arguments[] = 'bwd';
        }

        foreach ($this->filters as $filter) {
            $arguments[] = $filter;
        }

        return new SwissephCommand(
            executable: $this->executable,
            arguments: $arguments,
        );
    }

    private function validateFilters(EclipseKind $kind): void
    {
        $lunarValid = ['onlyTotal', 'onlyPartial', 'onlyPenumbral'];
        $solarValid = ['onlyTotal', 'onlyPartial', 'onlyAnnular', 'onlyHybrid', 'onlyCentral', 'onlyNonCentral'];
        $allowed = $kind === EclipseKind::LUNAR ? $lunarValid : $solarValid;

        foreach ($this->filterMethods as $method) {
            if (! in_array($method, $allowed, true)) {
                throw InvalidEclipseFilterException::notAllowed($method, $kind);
            }
        }
    }

    public function get(): EclipseCollection
    {
        $command = $this->build();

        $executor = $this->executor ?? app(SwissephExecutor::class);
        $parser = $this->parser ?? app(EclipseParser::class);

        // Per cross-plan notes §4: local modes pass ['geo. long'], global passes [].
        // The executor auto-skips the command-echo line already.
        $skipPrefixes = $this->scope === EclipseScope::LOCAL ? ['geo. long'] : [];
        $lines = $executor->run($command, $skipPrefixes);

        return $parser->parse($lines, $this->kind ?? EclipseKind::SOLAR, $this->scope);
    }

    public function getCliCommand(): string
    {
        return $this->build()->toCliString();
    }
}
