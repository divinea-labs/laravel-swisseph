<?php

namespace DivineaLabs\Swisseph\Data;

use Spatie\LaravelData\Data;

class SwissephCommand extends Data
{
    /**
     * @param  string  $executable  - Path to swetest binary executable
     * @param  string[]  $arguments  - Array of command line arguments without leading dashes
     */
    public function __construct(
        public string $executable,
        public array $arguments,
    ) {}

    /**
     * Konwersja na tablicę dla Symfony Process.
     */
    public function toProcessArray(): array
    {
        return array_merge(
            [$this->executable],
            array_map(fn ($arg) => "-{$arg}", $this->arguments),
        );
    }

    /**
     * Dobre do logowania lub debugowania.
     */
    public function toCliString(): string
    {
        $args = array_map(
            fn ($arg) => '-'.$arg,
            $this->arguments
        );

        return $this->executable.' '.implode(' ', $args);
    }
}
