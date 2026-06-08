<?php

declare(strict_types=1);

namespace DivineaLabs\Swisseph\Exceptions;

class OrbitalElementsBodyNotSetException extends \RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No body set for the orbital elements computation. Call forBody() before get().'
        );
    }
}
