<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class AstroTimeSeries extends Data
{
    /**
     * @param  list<AstroTimeFrame>  $frames  Time-ordered frames, one per `-n` step.
     */
    public function __construct(
        public readonly array $frames = [],
    ) {}

    public function count(): int
    {
        return count($this->frames);
    }

    /**
     * Look up a frame by its swetest timestamp token (`d.m.Y H:i:s`, e.g. `02.01.2026 12:00:00`).
     */
    public function at(string $timestamp): ?AstroTimeFrame
    {
        $needle = trim($timestamp);

        foreach ($this->frames as $frame) {
            if ($frame->date->format('d.m.Y H:i:s') === $needle) {
                return $frame;
            }
        }

        return null;
    }

    public function first(): ?AstroTimeFrame
    {
        return $this->frames[0] ?? null;
    }

    public function last(): ?AstroTimeFrame
    {
        $n = count($this->frames);

        return $n > 0 ? $this->frames[$n - 1] : null;
    }
}
