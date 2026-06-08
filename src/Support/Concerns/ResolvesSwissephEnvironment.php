<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Support\Concerns;

use Carbon\Carbon;
use DivineaLabs\Swisseph\Enums\EphOptions;
use DivineaLabs\Swisseph\Exceptions\InvalidEphOptionPassedException;

trait ResolvesSwissephEnvironment
{
    protected string $executable = '';

    protected string $epheDir = '';

    protected Carbon $dateTime;

    /** @var array<string, EphOptions> */
    protected array $ephOptions = [];

    // NOTE: location ($longitude/$latitude/$elevation/$place) is intentionally NOT in this
    // trait. Only the position/rising pipelines have a single geographic context; event
    // builders (eclipses/occultations/heliacal/meridian) carry their OWN nullable geo
    // fields so they can enforce "geopos required". Keeping location out of the trait
    // avoids fatal property-redeclaration clashes in those builders.

    protected function bootSwissephEnvironment(): void
    {
        $this->executable = (string) config('swisseph.executable', '');

        $dir = str_replace('\\', '/', (string) config('swisseph.ephemeris_dir', ''));
        $dir = rtrim($dir, '/');
        $this->epheDir = '/'.ltrim($dir, '/');

        foreach ((array) config('swisseph.eph_options', []) as $v) {
            if ($v instanceof EphOptions) {
                $this->ephOptions[$v->value] = $v;
            }
        }

        $this->dateTime = Carbon::now()->utc();
    }

    public function setDateTime(Carbon|string $date, string $tz = 'UTC'): static
    {
        $dt = is_string($date) ? Carbon::parse($date, $tz) : $date;
        $this->dateTime = $dt->utc();

        return $this;
    }

    public function withEphOptions(EphOptions|array ...$options): static
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

    protected function epheDirArg(): string
    {
        return 'edir'.$this->epheDir;
    }

    protected function dateArg(): string
    {
        return 'b'.$this->dateTime->format('d.m.Y');
    }

    protected function utTimeArg(): string
    {
        return 'ut'.$this->dateTime->format('H:i:s');
    }

    /** @return string[] */
    protected function ephOptionArgs(): array
    {
        $opts = array_values($this->ephOptions);
        usort($opts, static fn (EphOptions $a, EphOptions $b) => $a->value <=> $b->value);

        return array_map(static fn (EphOptions $o) => $o->value, $opts);
    }

    protected function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 8, '.', ''), '0'), '.');
    }

    public function getDate(): Carbon
    {
        return $this->dateTime;
    }
}
